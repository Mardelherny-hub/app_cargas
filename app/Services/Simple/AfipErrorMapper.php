<?php

namespace App\Services\Simple;

/**
 * AFIP Error Mapper - Mapeo de códigos de error AFIP a mensajes amigables
 * 
 * Traduce códigos de error técnicos de AFIP a mensajes comprensibles
 * para el usuario, incluyendo posibles soluciones.
 * 
 * Referencia: Manual AFIP wgesregsintia2
 */
class AfipErrorMapper
{
    /**
     * Mapeo de códigos de error AFIP a mensajes amigables
     */
    private const ERROR_MAP = [
        // Errores de datos/códigos
        '10021' => [
            'title' => 'Código de país inválido',
            'message' => 'El código de país informado no es válido según ISO 3166-1 Alfa 2.',
            'solution' => 'Verifique los códigos de país en puertos de origen y destino.',
        ],
        '10015' => [
            'title' => 'Código de aduana inválido',
            'message' => 'El código de aduana no es válido o no existe.',
            'solution' => 'Verifique el código de aduana en la configuración del puerto.',
        ],
        '12353' => [
            'title' => 'Lugar operativo incorrecto',
            'message' => 'El lugar operativo no corresponde a la aduana informada.',
            'solution' => 'Verifique que el lugar operativo coincida con la aduana.',
        ],
        
        // Errores de TRACKs
        '27130' => [
            'title' => 'TRACK inexistente',
            'message' => 'El número de TRACK informado no existe en el sistema AFIP.',
            'solution' => 'Ejecute RegistrarTitEnvios para generar los TRACKs necesarios.',
        ],
        
        // Errores de MIC/DTA
        '27173' => [
            'title' => 'MIC/DTA no existe',
            'message' => 'El MIC/DTA informado no existe en el sistema AFIP.',
            'solution' => 'Ejecute RegistrarMicDta primero.',
        ],
        '10747' => [
            'title' => 'Estado incorrecto del MIC/DTA',
            'message' => 'El MIC/DTA debe estar en estado Registrado para esta operación.',
            'solution' => 'Verifique el estado actual del MIC/DTA en AFIP.',
        ],
        
        // Errores de Convoy
        '27102' => [
            'title' => 'Convoy duplicado',
            'message' => 'Ya existe un convoy asociado a este MIC/DTA.',
            'solution' => 'No puede crear otro convoy. Use RectifConvoyMicDta para modificar.',
        ],
        '27175' => [
            'title' => 'Estado de convoy incorrecto',
            'message' => 'El convoy debe estar en estado Presentado para esta operación.',
            'solution' => 'Espere a que el Servicio Aduanero presente el convoy.',
        ],
        '27133' => [
            'title' => 'Rectificación no permitida',
            'message' => 'La rectificación solo puede realizarse antes de la salida.',
            'solution' => 'Ya se registró la salida, no se puede rectificar.',
        ],
        
        // Errores de transacción
        '41973' => [
            'title' => 'Transacción en proceso',
            'message' => 'Esta transacción ya se encuentra en proceso.',
            'solution' => 'Espere unos segundos y reintente, o use un nuevo ID de transacción.',
        ],
        '42034' => [
            'title' => 'Dato obligatorio faltante',
            'message' => 'Falta un dato obligatorio en la solicitud.',
            'solution' => 'Verifique que todos los campos requeridos estén completos.',
        ],
        
        // Errores de autenticación
        '10001' => [
            'title' => 'Error de autenticación',
            'message' => 'Las credenciales de autenticación son inválidas o expiraron.',
            'solution' => 'Verifique el certificado y regenere el token WSAA.',
        ],
        
        // Errores de CUIT
        '10003' => [
            'title' => 'CUIT inválido',
            'message' => 'El CUIT informado no es válido o no está habilitado.',
            'solution' => 'Verifique el CUIT de la empresa en la configuración.',
        ],
        
        // Errores de envío
        '27131' => [
            'title' => 'Envío no encontrado',
            'message' => 'El envío especificado no existe en el sistema.',
            'solution' => 'Verifique el ID de envío y que se haya registrado correctamente.',
        ],
        
        // Errores de contenedor
        '27140' => [
            'title' => 'Contenedor inválido',
            'message' => 'El número de contenedor no cumple con el formato ISO.',
            'solution' => 'Verifique que el contenedor tenga formato válido (ej: ABCD1234567).',
        ],
    ];

    /**
     * Obtener información de error para un código AFIP
     * 
     * @param string $code Código de error AFIP
     * @return array Información del error
     */
    public static function getErrorInfo(string $code): array
    {
        return self::ERROR_MAP[$code] ?? [
            'title' => 'Error AFIP ' . $code,
            'message' => 'Error no documentado del webservice AFIP.',
            'solution' => 'Contacte al administrador del sistema o consulte el manual AFIP.',
        ];
    }

    /**
     * Extraer código de error de respuesta SOAP
     * 
     * @param string $soapResponse Respuesta SOAP completa
     * @return string|null Código de error o null
     */
    public static function extractErrorCode(string $soapResponse): ?string
    {
        // Buscar patrón <Codigo>XXXXX</Codigo>
        if (preg_match('/<Codigo>(\d+)<\/Codigo>/', $soapResponse, $matches)) {
            return $matches[1];
        }
        
        // Buscar en listaErrores
        if (preg_match('/<codigo>(\d+)<\/codigo>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        
        // Buscar codigoError
        if (preg_match('/<codigoError>(\d+)<\/codigoError>/i', $soapResponse, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Extraer mensaje de error de respuesta SOAP
     * 
     * @param string $soapResponse Respuesta SOAP completa
     * @return string|null Mensaje de error o null
     */
    public static function extractErrorMessage(string $soapResponse): ?string
    {
        $patterns = [
            '/<Descripcion>([^<]+)<\/Descripcion>/i',
            '/<descripcion>([^<]+)<\/descripcion>/i',
            '/<mensaje>([^<]+)<\/mensaje>/i',
            '/<message>([^<]+)<\/message>/i',
            '/<faultstring>([^<]+)<\/faultstring>/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $soapResponse, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Procesar respuesta de error y devolver información completa
     * 
     * @param string $soapResponse Respuesta SOAP con error
     * @return array Información completa del error
     */
    public static function processErrorResponse(string $soapResponse): array
    {
        $code = self::extractErrorCode($soapResponse);
        $rawMessage = self::extractErrorMessage($soapResponse);
        
        if ($code) {
            $info = self::getErrorInfo($code);
            return [
                'code' => $code,
                'title' => $info['title'],
                'message' => $info['message'],
                'solution' => $info['solution'],
                'raw_message' => $rawMessage,
                'raw_response' => substr($soapResponse, 0, 500),
            ];
        }
        
        return [
            'code' => 'UNKNOWN',
            'title' => 'Error desconocido',
            'message' => $rawMessage ?? 'No se pudo interpretar la respuesta de AFIP.',
            'solution' => 'Revise los logs del sistema para más detalles.',
            'raw_message' => $rawMessage,
            'raw_response' => substr($soapResponse, 0, 500),
        ];
    }

    /**
     * Verificar si la respuesta contiene errores
     * 
     * @param string $soapResponse Respuesta SOAP
     * @return bool True si hay errores
     */
    public static function hasErrors(string $soapResponse): bool
    {
        return self::extractErrorCode($soapResponse) !== null ||
               stripos($soapResponse, 'soap:Fault') !== false ||
               stripos($soapResponse, '<listaErrores>') !== false;
    }

    /**
     * Obtener todos los códigos de error conocidos
     * 
     * @return array Lista de códigos
     */
    public static function getKnownErrorCodes(): array
    {
        return array_keys(self::ERROR_MAP);
    }
}