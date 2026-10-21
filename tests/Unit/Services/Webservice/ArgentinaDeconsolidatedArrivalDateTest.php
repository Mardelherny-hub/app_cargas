<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\Container;
use App\Models\DeconsolidationOperation;
use App\Models\Voyage;
use App\Services\Webservice\Argentina\SimpleXmlGeneratorDesconsolidado;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ArgentinaDeconsolidatedArrivalDateTest extends TestCase
{
    private function containerValidity(
        Voyage|DeconsolidationOperation $context,
        Container $container
    ): array {
        $company = new Company();
        $company->id = 101;
        $company->ws_environment = 'testing';
        $context->setRelation('company', $company);

        $generator = new SimpleXmlGeneratorDesconsolidado($context, [
            'environment' => 'testing',
        ]);

        return (new ReflectionMethod($generator, 'containerValidityData'))
            ->invoke($generator, $container, 'Conocimiento TEST: Contenedor ABCD1234567');
    }

    #[Test]
    public function independent_dossier_uses_only_its_documented_arrival_plus_480_days(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->arrival_date = '2026-10-20';
        $container = new Container();

        [$expiry, $acep] = $this->containerValidity($operation, $container);

        $this->assertSame('2028-02-12', $expiry->format('Y-m-d'));
        $this->assertNull($acep);
        $this->assertSame('2026-10-20', $operation->arrival_date->format('Y-m-d'));
    }

    #[Test]
    public function documented_csc_takes_precedence_over_dossier_arrival(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->arrival_date = '2026-10-20';
        $container = new Container();
        $container->csc_expiry_date = '2030-01-01';

        [$expiry, $acep] = $this->containerValidity($operation, $container);

        $this->assertSame('2030-01-01', $expiry->format('Y-m-d'));
        $this->assertNull($acep);
    }

    #[Test]
    public function documented_acep_takes_precedence_over_dossier_arrival(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->arrival_date = '2026-10-20';
        $container = new Container();
        $container->acep = 'ACEP123';

        [$expiry, $acep] = $this->containerValidity($operation, $container);

        $this->assertNull($expiry);
        $this->assertSame('ACEP123', $acep);
    }

    #[Test]
    public function missing_documented_arrival_blocks_unverifiable_fallback(): void
    {
        $operation = new DeconsolidationOperation();
        $container = new Container();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Ingresá la fecha documentada en el expediente');
        $this->containerValidity($operation, $container);
    }

    #[Test]
    public function voyage_flow_retains_its_existing_arrival_calculation(): void
    {
        $voyage = new Voyage();
        $voyage->estimated_arrival_date = '2026-10-20';
        $container = new Container();

        [$expiry, $acep] = $this->containerValidity($voyage, $container);

        $this->assertSame('2028-02-12', $expiry->format('Y-m-d'));
        $this->assertNull($acep);
    }

    #[Test]
    public function route_and_form_do_not_allow_posting_original_dossier_references(): void
    {
        $route = app('router')->getRoutes()->getByName(
            'company.simple.desconsolidado.operations.arrival-date'
        );

        $this->assertNotNull($route);
        $this->assertSame(['PATCH'], $route->methods()[0] === 'PATCH'
            ? ['PATCH'] : array_values(array_intersect($route->methods(), ['PATCH'])));
        $this->assertSame(
            'App\\Http\\Controllers\\Company\\Simple\\DeconsolidationOperationController@updateArrivalDate',
            $route->getActionName()
        );
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/show.blade.php'
        ));
        $this->assertStringContainsString("operations.arrival-date", $view);
        $this->assertStringContainsString('name="arrival_date"', $view);
        $this->assertStringContainsString("@method('PATCH')", $view);
    }
}
