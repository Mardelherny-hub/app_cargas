<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SoapClient;
use SoapFault;

class WsaaSmokeTest extends Command
{
    protected $signature = 'wsaa:smoke
        {--p12= : Ruta absoluta al archivo .p12}
        {--pass= : Password del .p12}
        {--service=wgesregsintia2 : Nombre del servicio (ej: wgesregsintia2)}
        {--wsdl=https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl : WSDL de WSAA}
        {--window=10 : Ventana de tiempo en minutos (±) para generation/expiration}
        {--chain= : Ruta a PEM con la(s) CA(s) intermedias de AFIP}';

    protected $description = 'WSAA smoke test: firma TRA (PKCS#7) y ejecuta loginCms para obtener token/sign';

    public function handle(): int
    {
        $p12Path = (string) $this->option('p12');
        $p12Pass = (string) $this->option('pass');
        $service = (string) $this->option('service');
        $wsdl    = (string) $this->option('wsdl');
        $window  = (int) $this->option('window');

        $chainPath = (string) $this->option('chain');
        $chainPem  = null;
        if ($chainPath !== '') {
            if (!is_file($chainPath)) {
                $this->error("No existe el archivo de cadena en: {$chainPath}");
                return self::FAILURE;
            }
            $chainPem = @file_get_contents($chainPath);
            if ($chainPem === false || trim($chainPem) === '') {
                $this->error("No se pudo leer la cadena PEM: {$chainPath}");
                return self::FAILURE;
            }
        }


        if ($p12Path === '' || $p12Pass === '') {
            $this->error('Faltan opciones: --p12 y --pass son obligatorias.');
            return self::FAILURE;
        }
        if (!is_file($p12Path)) {
            $this->error("No existe el archivo .p12 en: {$p12Path}");
            return self::FAILURE;
        }

        $this->info('[1] Cargando .p12…');
        $p12Raw = @file_get_contents($p12Path);
        if ($p12Raw === false) {
            $this->error('No se pudo leer el .p12 (permisos/ruta).');
            return self::FAILURE;
        }

        $creds = [];
        if (!openssl_pkcs12_read($p12Raw, $creds, $p12Pass)) {
            $this->error('openssl_pkcs12_read() falló (password o archivo inválido).');
            $this->dumpOpenSslErrors();
            return self::FAILURE;
        }
        $cert = $creds['cert'] ?? null;
        $pkey = $creds['pkey'] ?? null;
        if (!$cert || !$pkey) {
            $this->error('No se extrajeron cert/pkey del .p12.');
            return self::FAILURE;
        }
        $this->line('OK .p12 leído.');

        $this->info('[2] Armando TRA…');
        $now = time();
        $tra = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<loginTicketRequest version="1.0">'
             .   '<header>'
             .     '<uniqueId>'.($now).'</uniqueId>'
             .     '<generationTime>'.date('c', $now - ($window * 60)).'</generationTime>'
             .     '<expirationTime>'.date('c', $now + ($window * 60)).'</expirationTime>'
             .   '</header>'
             .   '<service>'.$this->xmlEscape($service).'</service>'
             . '</loginTicketRequest>';

        $tmpTra = tempnam(sys_get_temp_dir(), 'tra_') . '.xml';
        $tmpCms = tempnam(sys_get_temp_dir(), 'cms_') . '.p7m';
        file_put_contents($tmpTra, $tra);

        // [3] Firmar TRA en CMS (DER) con SHA-256 (cuando sea posible), DETACHED+BINARY, sin S/MIME
        $this->info('[3] Firmando TRA con CMS (DER, detached, binary)…');

        $cmsDerFile = tempnam(sys_get_temp_dir(), 'cms_der_'); // salida DER (binaria)
        $flagsCms   = (defined('OPENSSL_CMS_DETACHED') ? OPENSSL_CMS_DETACHED : 0)
                    | (defined('OPENSSL_CMS_BINARY')   ? OPENSSL_CMS_BINARY   : 0);

        $cms = '';

        $opensslVersionText = OPENSSL_VERSION_TEXT ?? phpversion('openssl') ?? 'unknown';
        $this->line('OpenSSL: ' . $opensslVersionText);

        // Camino A: usar openssl_cms_sign (8 args)
        if (function_exists('openssl_cms_sign')) {
            // Firma en DER (8° argumento = encoding). Sin opciones extra porque en tu PHP acepta máx 8 args.
            $encoding = defined('OPENSSL_ENCODING_DER') ? OPENSSL_ENCODING_DER : 0;

            $ok = @openssl_cms_sign(
                $tmpTra,     // in_filename
                $cmsDerFile, // out_filename (DER)
                $cert,       // cert PEM
                $pkey,       // key PEM
                [],          // headers S/MIME (no usamos)
                $flagsCms,   // DETACHED + BINARY
                0,           // cipherid (no aplica)
                $encoding    // DER
            );

            if ($ok) {
                // Nota: el digest por default en CMS sobre OpenSSL ≥1.1 suele ser SHA-256
                $der = @file_get_contents($cmsDerFile);
                if ($der !== false && strlen($der) > 0) {
                    $cms = base64_encode($der);
                }
            } else {
                $this->warn('openssl_cms_sign() no disponible o falló; intento con CLI.');
                $this->dumpOpenSslErrors();
            }
        }

        // Camino B (fallback): CLI "openssl cms -sign -md sha256"
        if ($cms === '') {
            // volcamos cert y key PEM a archivos temporales
        $certPemFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pem';
        $keyPemFile  = tempnam(sys_get_temp_dir(), 'key_')  . '.pem';
        file_put_contents($certPemFile, $cert);
        file_put_contents($keyPemFile,  $pkey);

        // si vino cadena, la guardamos para -certfile (puede contener 1+ "BEGIN CERTIFICATE")
        $chainPemFile = null;
        $certfileArg  = '';
        if ($chainPem) {
            $chainPemFile = tempnam(sys_get_temp_dir(), 'chain_') . '.pem';
            file_put_contents($chainPemFile, $chainPem);
            $certfileArg = ' -certfile ' . escapeshellarg($chainPemFile);
        }

        // Firma CMS **adjunta**, DER, SHA-256, incluyendo cert del firmante y (si hay) la cadena
        $cmd = sprintf(
            'openssl cms -sign -binary -in %s -signer %s -inkey %s%s -outform DER -out %s -md sha256 -nosmimecap -nodetach',
            escapeshellarg($tmpTra),
            escapeshellarg($certPemFile),
            escapeshellarg($keyPemFile),
            $certfileArg, // <-- cadena
            escapeshellarg($cmsDerFile)
        );
        $this->line('Ejecutando: ' . $cmd);
        exec($cmd . ' 2>&1', $out, $ret);
        if ($ret !== 0) {
            $this->error('openssl cms -sign falló (CLI). Salida: ' . implode("\n", $out));
            @unlink($certPemFile); @unlink($keyPemFile);
            if ($chainPemFile) @unlink($chainPemFile);
            return self::FAILURE;
        }
        $der = @file_get_contents($cmsDerFile);
        if ($der === false || strlen($der) === 0) {
            $this->error('No se generó CMS DER desde la CLI.');
            @unlink($certPemFile); @unlink($keyPemFile);
            if ($chainPemFile) @unlink($chainPemFile);
            return self::FAILURE;
        }
        $cms = base64_encode($der);

        @unlink($certPemFile); @unlink($keyPemFile);
        if ($chainPemFile) @unlink($chainPemFile);

}

if ($cms === '') {
    $this->error('No se pudo obtener CMS DER/base64.');
    return self::FAILURE;
}

$this->line('CMS listo (DER/base64, detached).');


        $this->info('[4] Invocando WSAA loginCms…');
        try {
            $client = new SoapClient($wsdl, [
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => 20,
                'cache_wsdl' => WSDL_CACHE_MEMORY,
            ]);

            $resp = $client->loginCms(['in0' => $cms]);
            if (!isset($resp->loginCmsReturn)) {
                $this->error('Respuesta sin loginCmsReturn.');
                $this->dumpLastMessages($client);
                return self::FAILURE;
            }

            $loginReturn = (string) $resp->loginCmsReturn;
            $xml = @simplexml_load_string($loginReturn);
            if ($xml === false) {
                $this->error('loginCmsReturn no es XML válido.');
                $this->dumpLastMessages($client);
                return self::FAILURE;
            }

            $token = (string) ($xml->credentials->token ?? '');
            $sign  = (string) ($xml->credentials->sign ?? '');
            $exp   = (string) ($xml->header->expirationTime ?? '');

            if ($token === '' || $sign === '') {
                $this->warn('WSAA respondió pero token/sign están vacíos.');
                $this->dumpLastMessages($client);
                return self::FAILURE;
            }

            $this->info('✅ OK loginCms – token/sign obtenidos');
            $this->line('Expira: ' . $exp);
            $this->newLine();
            $this->line('Token (primeros 64): ' . substr($token, 0, 64) . '…');
            $this->line('Sign  (primeros 64): ' . substr($sign, 0, 64) . '…');

            // Log útil para depuración futura:
            \Log::channel('single')->info('WSAA Smoke OK', [
                'service' => $service,
                'expires' => $exp,
                'token_prefix' => substr($token, 0, 16),
                'sign_prefix'  => substr($sign, 0, 16),
            ]);

            return self::SUCCESS;

        } catch (SoapFault $sf) {
            $this->error("SOAPFault: {$sf->faultcode} – {$sf->faultstring}");
            if (isset($client)) {
                $this->dumpLastMessages($client);
            }
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Excepción: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            // Limpieza
            @unlink($tmpTra);
            @unlink($tmpCms);
        }
    }

    private function stripSmimeHeaders(string $raw): string
    {
        // Quita encabezados tipo:
        // MIME-Version: 1.0
        // Content-Type: multipart/signed; ...
        // ...
        // (línea en blanco)
        $parts = preg_split("/\R\R/", $raw, 2); // divide en primera línea en blanco
        $body  = $parts[1] ?? $raw;
        $body  = trim($body);
        // Remueve saltos y espacios
        $body  = str_replace(["\r", "\n"], '', $body);
        return $body;
    }

    private function dumpLastMessages(SoapClient $client): void
    {
        $this->line('---- LastRequest ----');
        $this->line($client->__getLastRequest() ?: '(vacío)');
        $this->line('---- LastResponse ----');
        $this->line($client->__getLastResponse() ?: '(vacío)');
    }

    private function dumpOpenSslErrors(): void
    {
        while ($e = openssl_error_string()) {
            $this->line('OpenSSL: ' . $e);
        }
    }

    private function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * Extrae el bloque base64 de la firma PKCS#7 desde un mensaje S/MIME multipart/signed
     * generado por openssl_pkcs7_sign(). Devuelve SOLO el contenido base64 de la parte
     * "application/x-pkcs7-signature".
     */
    private function extractCmsFromSmime(string $raw): string
    {
        // Normalizamos saltos
        $raw = str_replace("\r\n", "\n", $raw);

        // 1) Separar encabezados iniciales del S/MIME
        $pos = strpos($raw, "\n\n");
        if ($pos === false) {
            // No hay doble salto => devolvemos intento "rápido": solo base64 largo
            return $this->greedyBase64($raw);
        }
        $headers = substr($raw, 0, $pos);
        $bodyAll = substr($raw, $pos + 2);

        // 2) Buscar boundary en Content-Type
        $matches = [];
        if (!preg_match('/boundary="?([^"\n]+)"?/i', $headers, $matches)) {
            // Sin boundary => fallback greedy
            return $this->greedyBase64($raw);
        }
        $boundary = $matches[1];

        // 3) Partir por boundary
        $parts = preg_split('/\n--' . preg_quote($boundary, '/') . '(?:--)?\n/', "\n" . $bodyAll);
        if (!$parts || count($parts) < 2) {
            // No se pudo partir: fallback greedy
            return $this->greedyBase64($raw);
        }

        // 4) Recorrer partes y quedarnos con la firma
        foreach ($parts as $part) {
            $part = ltrim($part, "\n"); // limpia posibles saltos iniciales
            if ($part === '' || str_starts_with($part, '--')) { continue; }

            $pPos = strpos($part, "\n\n");
            if ($pPos === false) { continue; }
            $pHeaders = substr($part, 0, $pPos);
            $pBody    = substr($part, $pPos + 2);

            // ¿Es la parte de la firma?
            if (stripos($pHeaders, 'application/x-pkcs7-signature') !== false
                || stripos($pHeaders, 'smime.p7s') !== false) {

                // Devolver solo base64 (sin saltos/espacios)
                $b64 = preg_replace('/\s+/', '', $pBody);
                // Validación mínima: largo múltiplo de 4 y caracteres válidos base64
                if ($b64 !== '' && preg_match('/^[A-Za-z0-9+\/=]+$/', $b64) && (strlen($b64) % 4 === 0)) {
                    return $b64;
                }
            }
        }

        // 5) Si no encontramos la parte, último intento greedy
        return $this->greedyBase64($raw);
    }

    /**
     * Fallback: intenta encontrar el último bloque base64 "largo" en el texto,
     * asumiendo que corresponde al PKCS#7.
     */
    private function greedyBase64(string $raw): string
    {
        // Quitamos todo lo que no sea base64 y nos quedamos con el bloque más largo
        $candidates = [];
        if (preg_match_all('/([A-Za-z0-9+\/=\s]{512,})/', $raw, $m)) {
            foreach ($m[1] as $chunk) {
                $b64 = preg_replace('/\s+/', '', $chunk);
                if ($b64 !== '' && preg_match('/^[A-Za-z0-9+\/=]+$/', $b64) && (strlen($b64) % 4 === 0)) {
                    $candidates[] = $b64;
                }
            }
        }
        if (empty($candidates)) { return ''; }
        // Devolvemos el más largo (suele ser la firma)
        usort($candidates, fn($a, $b) => strlen($b) <=> strlen($a));
        return $candidates[0];
    }

}
