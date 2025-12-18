<?php

namespace App\Services\Simple\Soap;

use SoapClient;

class ParaguaySecureSoapClient extends SoapClient
{
    private ParaguayWSSecurityBuilder $securityBuilder;

    public function __construct(string $wsdl, array $options, ParaguayWSSecurityBuilder $securityBuilder)
    {
        $this->securityBuilder = $securityBuilder;
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
        
        if (false && $this->shouldSecurePayload($request)) {
            \Log::info('Aplicando WS-Security al request');
            $request = $this->securityBuilder->secure($request);
            
            // AGREGAR ESTAS 3 LÍNEAS AQUÍ:
            $xmlPath = storage_path('logs/soap_request_' . date('YmdHis') . '.xml');
            file_put_contents($xmlPath, $request);
            \Log::info('XML SOAP guardado', ['path' => $xmlPath]);
        } else {
            \Log::warning('NO se aplicó WS-Security - shouldSecurePayload = false');
        }

        return parent::__doRequest($request, $location, $action, $version, $one_way);
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
}
