<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\Simple\DeconsolidationContainerLinkController;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Contrato HTTP seguro del recorrido ATA-DESC.
 * No accede a la base de datos ni transmite a Aduana.
 * La escritura real y la asociación requieren una BD de test aislada.
 */
class ArgentinaDeconsolidatedContainerFlowTest extends TestCase
{
    private const PREFIX = 'company.simple.desconsolidado.operations.bills.items.containers.';

    public function test_routes_match_distinct_existing_and_new_container_flows(): void
    {
        $routes = app('router')->getRoutes();
        $cases = [
            ['GET', 'create', '/containers/create', 'create'],
            ['POST', 'store', '/containers', 'store'],
            ['GET', 'register', '/containers/register', 'register'],
            ['POST', 'store-new', '/containers/register', 'storeNew'],
        ];

        foreach ($cases as [$method, $name, $suffix, $action]) {
            $named = $routes->getByName(self::PREFIX . $name);
            $this->assertNotNull($named, "Falta la ruta {$name}");
            $uri = '/company/simple/webservices/desconsolidado/operations/1/bills/2/items/3' . $suffix;
            $resolved = $routes->match(Request::create($uri, $method));
            $this->assertSame($named->getName(), $resolved->getName());
            $this->assertSame(
                DeconsolidationContainerLinkController::class . '@' . $action,
                $resolved->getActionName()
            );
        }
    }

    public function test_linking_form_offers_registration_without_posting_to_customs(): void
    {
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/bills/items/containers/create.blade.php'
        ));
        $this->assertStringContainsString(self::PREFIX . 'register', $view);
        $this->assertStringContainsString('Registrar contenedor nuevo', $view);
        $this->assertStringContainsString("'operation' => \$operation->id", $view);
        $this->assertStringContainsString("'bill' => \$bill->id", $view);
        $this->assertStringContainsString("'item' => \$item->id", $view);
    }

    public function test_new_registration_form_targets_only_its_own_post_route(): void
    {
        $view = file_get_contents(resource_path(
            'views/company/simple/desconsolidado/operations/bills/items/containers/register.blade.php'
        ));
        $this->assertStringContainsString('method="POST"', $view);
        $this->assertStringContainsString(self::PREFIX . 'store-new', $view);
        $this->assertStringContainsString(self::PREFIX . 'create', $view);
        $this->assertStringNotContainsString('RegistrarTitulosDesconsolidador', $view);
    }
}
