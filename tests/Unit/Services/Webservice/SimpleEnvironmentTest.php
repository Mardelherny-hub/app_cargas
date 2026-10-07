<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\User;
use App\Services\Simple\ArgentinaAnticipatedService;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use App\Services\Simple\ArgentinaMicDtaService;
use App\Services\Simple\ArgentinaMicDtaStatusService;
use App\Services\Simple\ParaguayDnaService;
use App\Services\Simple\ParaguayWsaaService;
use App\Services\Simple\SimpleXmlGenerator;
use App\Services\Simple\WebserviceEnvironment;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class SimpleEnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'null', 'audit.enabled' => false]);
    }

    private function company(?string $environment): Company
    {
        $company = new Company();
        $company->forceFill(['id' => 1, 'ws_environment' => $environment]);
        return $company;
    }

    private function configOf(object $service): array
    {
        return (new ReflectionProperty($service, 'config'))->getValue($service);
    }

    private function invoke(object $service, string $method): mixed
    {
        return (new ReflectionMethod($service, $method))->invoke($service);
    }

    public function test_all_simple_services_use_company_switch_despite_opposite_overrides(): void
    {
        foreach (['testing', 'production'] as $environment) {
            $opposite = $environment === 'testing' ? 'production' : 'testing';
            config(['services.paraguay.environment' => $opposite,
                'services.paraguay.wsdl' => 'https://wrong.invalid/?wsdl']);
            $company = $this->company($environment);
            foreach ([ArgentinaAnticipatedService::class, ArgentinaMicDtaService::class,
                ArgentinaMicDtaStatusService::class, ArgentinaDeconsolidatedService::class,
                ParaguayDnaService::class] as $class) {
                $service = new $class($company, new User(), [
                    'environment' => $opposite,
                    'webservice_url' => 'https://wrong.invalid/',
                    'wsdl_url' => 'https://wrong.invalid/?wsdl',
                ]);
                $config = $this->configOf($service);
                $this->assertSame($environment, $config['environment'], $class);
                $method = $class === ArgentinaAnticipatedService::class ? 'getServiceEndpoint' : 'getWsdlUrl';
                $endpoint = $this->invoke($service, $method);
                $expectedHost = $class === ParaguayDnaService::class
                    ? ($environment === 'testing' ? 'securetest.aduana.gov.py' : 'secure.aduana.gov.py')
                    : ($environment === 'testing' ? 'wsaduhomoext.afip.gob.ar' : 'webservicesadu.afip.gob.ar');
                $this->assertSame($expectedHost, parse_url($endpoint, PHP_URL_HOST), $class);
                // Anticipada y Status obtienen el endpoint mediante su método, no esta opción.
                if (isset($config['webservice_url']) && !in_array($class, [
                    ArgentinaAnticipatedService::class, ArgentinaMicDtaStatusService::class,
                ], true)) {
                    $this->assertSame($expectedHost, parse_url($config['webservice_url'], PHP_URL_HOST));
                }
            }
            $generator = new SimpleXmlGenerator($company, ['environment' => $opposite]);
            $this->assertSame($environment, $this->configOf($generator)['environment']);
            $wsaa = new ParaguayWsaaService($company, $opposite);
            $this->assertSame($environment, (new ReflectionProperty($wsaa, 'environment'))->getValue($wsaa));
            $this->assertSame($environment === 'testing' ? 'securetest.aduana.gov.py' : 'secure.aduana.gov.py',
                parse_url($this->invoke($wsaa, 'getWsaaUrl'), PHP_URL_HOST));
        }
    }

    public function test_invalid_or_missing_switch_is_rejected_even_with_valid_override(): void
    {
        foreach ([null, '', 'test', 'staging', 'PRODUCTION'] as $invalid) {
            foreach ([ArgentinaAnticipatedService::class, ArgentinaMicDtaService::class,
                ArgentinaMicDtaStatusService::class, ArgentinaDeconsolidatedService::class,
                ParaguayDnaService::class] as $class) {
                try {
                    new $class($this->company($invalid), new User(), ['environment' => 'testing']);
                    $this->fail("Se aceptó un ambiente inválido en {$class}");
                } catch (InvalidArgumentException $e) {
                    $this->assertStringContainsString('ambiente de Aduana válido', $e->getMessage());
                }
            }
        }
    }

    public function test_argentina_endpoints_keep_the_correct_service_in_both_environments(): void
    {
        foreach (['testing', 'production'] as $environment) {
            foreach (['wgesregsintia2', 'wgesinformacionanticipada'] as $service) {
                $this->assertStringEndsWith("/DIAV2/{$service}/{$service}.asmx",
                    WebserviceEnvironment::argentinaEndpoint($this->company($environment), $service));
            }
        }
    }
}
