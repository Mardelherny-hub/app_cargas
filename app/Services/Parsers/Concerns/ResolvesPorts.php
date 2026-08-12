<?php

namespace App\Services\Parsers\Concerns;

use App\Models\Port;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Resolución de puertos por código, compartida por los parsers.
 *
 * Política del proyecto (30/07/2026): los parsers NUNCA crean puertos. El
 * catálogo tiene ~17.500 registros con sus códigos AFIP y de aduana; un puerto
 * creado al vuelo no los tiene y rompe la transmisión sin que nadie lo note.
 * Un código desconocido debe fallar con un mensaje claro.
 *
 * Los aliases son códigos propios de los generadores, no UN/LOCODE. Todos
 * verificados contra archivos reales y contra el catálogo.
 */
trait ResolvesPorts
{
    protected array $portAliases = [
        'ARBAI' => 'ARBUE',   // "BUENOS AIRES"
        'PYTV'  => 'PYTVT',   // "TERPORT-VILLETA"
        'PYTVI' => 'PYTVT',   // "TERPORT VILLETA"
        'PYSEF' => 'PYPSE',   // "PUERTO SEGURO FLUVIAL"
        'PYNNV' => 'PYVLL',   // "ANNP VILLETA" = puerto publico de Villeta (verificado 11/08/2026 contra BM ROSA V.468)
    ];

/**
     * Devuelve el puerto o lanza excepción con un mensaje que le dice al usuario
     * qué hacer, incluyendo los códigos parecidos del catálogo.
     *
     * No se crea el puerto: un puerto sin código de aduana pasa la importación
     * pero hace que la aduana rechace el manifiesto después, cuando ya nadie
     * está mirando. Es preferible frenar acá con una indicación clara.
     */
    protected function resolvePortStrict(?string $code): Port
    {
        $original = strtoupper(trim((string) $code));

        if ($original === '') {
            throw new Exception('El archivo no declara el código de puerto.');
        }

        $resolved = $this->portAliases[$original] ?? $original;

        $port = Port::where('code', $resolved)->where('active', true)->first();

        if ($port) {
            if ($resolved !== $original) {
                Log::info('Puerto mapeado por alias', [
                    'codigo_archivo'  => $original,
                    'codigo_resuelto' => $resolved,
                    'port_id'         => $port->id,
                ]);
            }
            return $port;
        }

        throw new Exception($this->mensajePuertoDesconocido($original));
    }

    /**
     * Arma un mensaje con los códigos parecidos del catálogo, para que el
     * usuario pueda identificar cuál corresponde sin salir de la pantalla.
     */
    protected function mensajePuertoDesconocido(string $code): string
    {
        $pais = substr($code, 0, 2);

        $candidatos = Port::where('code', 'like', $pais . '%')
            ->where('active', true)
            ->pluck('name', 'code')
            ->all();

        $parecidos = [];
        if ($candidatos) {
            $codigos = array_keys($candidatos);
            $cercanos = [];
            foreach ($codigos as $c) {
                similar_text($code, $c, $pct);
                $cercanos[$c] = $pct;
            }
            arsort($cercanos);
            foreach (array_slice(array_keys($cercanos), 0, 3) as $c) {
                $parecidos[] = "{$c} ({$candidatos[$c]})";
            }
        }

        $msg = "El código de puerto '{$code}' no está en el catálogo del sistema. ";

        if ($parecidos) {
            $msg .= 'Puertos parecidos: ' . implode(', ', $parecidos) . '. ';
        }

        $msg .= 'Si el puerto es correcto, solicite el alta al administrador '
              . 'indicando el código y el nombre; no se dan de alta automáticamente '
              . 'porque necesitan el código de aduana para poder transmitir.';

        return $msg;
    }

    /**
     * Variante que devuelve null en lugar de fallar. Para campos opcionales
     * (ej. destino final), donde la ausencia no debe abortar la importación.
     */
    protected function resolvePortOrNull(?string $code): ?Port
    {
        try {
            return $this->resolvePortStrict($code);
        } catch (Exception $e) {
            Log::warning('Puerto opcional no resuelto', ['code' => $code, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
