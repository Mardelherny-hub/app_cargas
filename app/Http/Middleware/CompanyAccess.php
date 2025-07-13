<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$companyParams): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super admin can access everything
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Company admin and users need to verify company access
        if ($user->hasRole('company-admin') || $user->hasRole('user')) {
            $companyId = $this->getCompanyIdFromRequest($request, $companyParams);

            // Si hay un companyId específico en la ruta, verificar acceso
            if ($companyId) {
                if (!$user->belongsToCompany($companyId)) {
                    abort(403, 'No tiene permisos para acceder a esta empresa.');
                }
                return $next($request);
            }

            // CORREGIDO: Para rutas sin companyId específico (como /company/dashboard)
            // verificar que el usuario tenga acceso general a empresas
            if ($this->userHasCompanyAccess($user)) {
                return $next($request);
            }

            // Si el usuario no tiene acceso a ninguna empresa
            abort(403, 'No tiene permisos para acceder al área de empresas. Contacte al administrador.');
        }

        // If user has no recognized role, deny access
        abort(403, 'No tiene permisos para acceder a esta sección.');
    }

    /**
     * Verificar si el usuario tiene acceso general a empresas.
     * CORREGIDO: Simplificado según el nuevo modelo de negocio.
     */
    private function userHasCompanyAccess($user): bool
    {
        // Company admin debe tener empresa asociada
        if ($user->hasRole('company-admin')) {
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                return $user->userable->active;
            }
            return false;
        }

        // CORREGIDO: Users (operadores) deben tener empresa asociada válida
        if ($user->hasRole('user')) {
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $operator = $user->userable;

                // Verificar que el operador está activo
                if (!$operator->active) {
                    return false;
                }

                // CORREGIDO: Todos los operadores deben tener company_id válido
                if (!$operator->company_id) {
                    return false;
                }

                // Verificar que la empresa existe y está activa
                $company = $operator->company;
                return $company && $company->active;
            }

            // Si es usuario directo de empresa
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                return $user->userable->active;
            }

            return false;
        }

        return false;
    }

    /**
     * Obtener el ID de empresa desde la request.
     */
    private function getCompanyIdFromRequest($request, $companyParams): ?int
    {
        // Si hay parámetros específicos de empresa en la ruta
        foreach ($companyParams as $param) {
            if ($request->route($param)) {
                return (int) $request->route($param);
            }
        }

        // Si hay un parámetro 'company' en la ruta
        if ($request->route('company')) {
            return (int) $request->route('company');
        }

        return null;
    }
}
