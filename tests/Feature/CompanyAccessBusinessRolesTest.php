<?php

namespace Tests\Feature;

use App\Http\Middleware\CompanyAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CompanyAccessBusinessRolesTest extends TestCase
{
    #[DataProvider('rolesAndRoutes')]
    public function test_company_role_access(array $roles, string $routeName, int $expectedStatus): void
    {
        $company = new Company();
        $company->company_roles = $roles;
        $company->active = true;

        $user = \Mockery::mock(User::class)->makePartial();
        $user->userable_type = Company::class;
        $user->setRelation('userable', $company);
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        $user->shouldReceive('hasRole')->with('company-admin')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $route = Route::get('/role-contract', fn () => new Response('ok'));
        $route->name($routeName);
        $route->bind(Request::create('/role-contract', 'GET'));
        $request = Request::create('/role-contract', 'GET');
        $request->setRouteResolver(fn () => $route);

        try {
            $response = (new CompanyAccess())->handle($request, fn () => new Response('ok'));
            $this->assertSame($expectedStatus, $response->getStatusCode());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame($expectedStatus, $exception->getStatusCode());
        }
    }

    public static function rolesAndRoutes(): array
    {
        return [
            'cargas-only cannot use transbordos' => [['Cargas'], 'company.simple.transbordo.index', 403],
            'transbordos-only can use transbordos' => [['Transbordos'], 'company.simple.transbordo.index', 200],
            'cargas-and-transbordos can use transbordos' => [['Cargas', 'Transbordos'], 'company.simple.transbordo.index', 200],
            'desconsolidador-only cannot use cargas' => [['Desconsolidador'], 'company.voyages.index', 403],
            'transbordos-only cannot use cargas' => [['Transbordos'], 'company.shipments.index', 403],
            'desconsolidador-only can use its own route' => [['Desconsolidador'], 'company.simple.desconsolidado.operations.index', 200],
            'cargas-only can use cargas' => [['Cargas'], 'company.voyages.index', 200],
        ];
    }
}
