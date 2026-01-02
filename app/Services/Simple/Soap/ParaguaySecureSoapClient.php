<?php

namespace App\Services\Simple\Soap;

use SoapClient;

class ParaguaySecureSoapClient extends SoapClient
{
    private ParaguayWSSecurityBuilder $securityBuilder;
    private string $clientPrivateKey;

    public function __construct(string $wsdl, array $options, ParaguayWSSecurityBuilder $securityBuilder, string $clientPrivateKey = '')
    {
        $this->securityBuilder = $securityBuilder;
        $this->clientPrivateKey = $clientPrivateKey;
        parent::__construct($wsdl, $options);
    }

    #[\ReturnTypeWillChange]
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        \Log::info('ParaguaySecureSoapClient::__doRequest llamado', [
            'should_secure' => $this->shouldSecurePayload($request),
            'has_envelope' => preg_match('/<[^>]:Envelope/i', $request ?? ''),
            'request_length' => strlen($request ?? ''),
        ]);
        
        if ($this->shouldSecurePayload($request)) {
            \Log::info('Aplicando WS-Security al request');
            $request = $this->securityBuilder->secure($request);
            
            // AGREGAR ESTAS 3 LÍNEAS AQUÍ:
            $xmlPath = storage_path('logs/soap_request_' . date('YmdHis') . '.xml');
            file_put_contents($xmlPath, $request);
            \Log::info('XML SOAP guardado', ['path' => $xmlPath]);
        } else {
            \Log::warning('NO se aplicó WS-Security - shouldSecurePayload = false');
        }

        $response = parent::__doRequest($request, $location, $action, $version, $one_way);

        // Desencriptar respuesta si tenemos clave privada
        if ($response && $this->clientPrivateKey && $this->isEncryptedResponse($response)) {
            try {
                \Log::info('ParaguaySecureSoapClient: Desencriptando respuesta');
                $decryptor = new ParaguayWSSecurityDecryptor($this->clientPrivateKey);
                $response = $decryptor->decrypt($response);
                
                // Guardar respuesta desencriptada para debug
                $xmlPath = storage_path('logs/soap_response_decrypted_' . date('YmdHis') . '.xml');
                file_put_contents($xmlPath, $response);
                \Log::info('Respuesta desencriptada guardada', ['path' => $xmlPath]);
            } catch (\Exception $e) {
                \Log::error('Error desencriptando respuesta', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }
    private function shouldSecurePayload(?string $request): bool
    {
        
        if (!$request) {
            return false;
        }

        \Log::info('shouldSecurePayload - analizando request', [
            'first_200_chars' => substr($request, 0, 200),
            'has_soap_env' => strpos($request, 'Envelope') !== false,
        ]);

        return (bool) preg_match('/<[^>]+:Envelope/i', $request);
    }

    private function isEncryptedResponse(?string $response): bool
    {
        if (!$response) {
            return false;
        }
        return strpos($response, 'EncryptedData') !== false || strpos($response, 'EncryptedKey') !== false;
    }
}
