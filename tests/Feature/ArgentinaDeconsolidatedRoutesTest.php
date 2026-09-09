<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\Simple\ArgentinaDeconsolidatedController;
use App\Http\Controllers\Company\Simple\LegacyDeconsolidationRedirectController;
use Illuminate\Http\Request;
use Tests\TestCase;

class ArgentinaDeconsolidatedRoutesTest extends TestCase
{
    public function test_named_routes_resolve_to_the_canonical_controller(): void
    {
        $routes = app('router')->getRoutes();

        $show = $routes->getByName('company.simple.desconsolidado.show');
        $this->assertNotNull($show);
        $this->assertSame(
            'company/simple/webservices/desconsolidado/{voyage}',
            $show->uri()
        );
        $this->assertSame(
            ArgentinaDeconsolidatedController::class . '@show',
            $show->getActionName()
        );

        $send = $routes->getByName('company.simple.desconsolidado.send');
        $this->assertNotNull($send);
        $this->assertSame(
            'company/simple/webservices/desconsolidado/{voyage}/send',
            $send->uri()
        );
        $this->assertSame(
            ArgentinaDeconsolidatedController::class . '@send',
            $send->getActionName()
        );

        $containerCustoms = $routes->getByName(
            'company.simple.desconsolidado.container-customs'
        );
        $this->assertNotNull($containerCustoms);
        $this->assertSame(
            ArgentinaDeconsolidatedController::class . '@saveContainerCustoms',
            $containerCustoms->getActionName()
        );
    }

    public function test_http_matching_uses_the_canonical_controller_for_show_and_send(): void
    {
        $routes = app('router')->getRoutes();

        $show = $routes->match(Request::create(
            '/company/simple/webservices/desconsolidado/123',
            'GET'
        ));
        $this->assertSame(
            ArgentinaDeconsolidatedController::class . '@show',
            $show->getActionName()
        );

        $send = $routes->match(Request::create(
            '/company/simple/webservices/desconsolidado/123/send',
            'POST'
        ));
        $this->assertSame(
            ArgentinaDeconsolidatedController::class . '@send',
            $send->getActionName()
        );
    }

    public function test_legacy_deconsolidation_routes_cannot_reach_the_fake_controller(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'company.deconsolidation.index',
            'company.deconsolidation.store',
            'company.deconsolidation.destroy',
            'company.deconsolidation.update-status',
        ] as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Falta la ruta legacy {$name}");
            $this->assertStringContainsString(
                LegacyDeconsolidationRedirectController::class,
                $route->getActionName()
            );
            $this->assertStringNotContainsString(
                'DeconsolidationController',
                str_replace(LegacyDeconsolidationRedirectController::class, '', $route->getActionName())
            );
        }
    }

    public function test_http_matching_neutralizes_legacy_read_and_write_endpoints(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            ['GET', '/company/deconsolidation'],
            ['POST', '/company/deconsolidation'],
            ['DELETE', '/company/deconsolidation/123'],
            ['PATCH', '/company/deconsolidation/123/status'],
        ] as [$method, $uri]) {
            $route = $routes->match(Request::create($uri, $method));
            $this->assertStringContainsString(
                LegacyDeconsolidationRedirectController::class,
                $route->getActionName(),
                "{$method} {$uri} no quedó neutralizada"
            );
        }
    }
}
