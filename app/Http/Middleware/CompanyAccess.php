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

            // CORRECCIÓN: Si hay un companyId específico en la ruta, verificar acceso
            if ($companyId) {
                if (!$user->belongsToCompany($companyId)) {
                    abort(403, 'No tiene permisos para acceder a esta empresa.');
                }
                return $next($request);
            }

            // CORRECCIÓN: Para rutas sin companyId específico (como /company/dashboard)
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
     * NUEVO MÉTODO: Esta es la corrección principal
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

        // Users (operadores) deben tener empresa asociada válida
        if ($user->hasRole('user')) {
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $operator = $user->userable;

                // Verificar que el operador está activo
                if (!$operator->active) {
                    return false;
                }

                // Operadores externos deben tener company_id válido
                if ($operator->type === 'external') {
                    return $operator->company_id &&
                           \App\Models\Company::where('id', $operator->company_id)
                                             ->where('active', true)
                                             ->exists();
                }

                // Operadores internos pueden acceder si hay empresas activas en el sistema
                if ($operator->type === 'internal') {
                    return \App\Models\Company::where('active', true)->exists();
                }
            }

            // Usuario directo de empresa (raro pero posible)
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                return $user->userable->active;
            }

            return false;
        }

        return false;
    }

    /**
     * Get company ID from request
     * MÉTODO EXISTENTE: Se mantiene igual
     */
    private function getCompanyIdFromRequest(Request $request, array $companyParams): ?int
    {
        // Search in route parameters
        foreach ($companyParams as $param) {
            if ($request->route($param)) {
                return (int) $request->route($param);
            }
        }

        // Search in query parameters
        if ($request->has('company_id')) {
            return (int) $request->get('company_id');
        }

        // Search in related models
        $routeKeys = ['trip', 'shipment', 'container', 'company'];
        foreach ($routeKeys as $key) {
            if ($request->route($key)) {
                $model = $request->route($key);

                // Direct company_id property
                if ($model && property_exists($model, 'company_id')) {
                    return $model->company_id;
                }

                // Through company relationship
                if ($model && method_exists($model, 'company') && $model->company) {
                    return $model->company->id;
                }

                // If the model is a Company itself
                if ($model && $model instanceof \App\Models\Company) {
                    return $model->id;
                }
            }
        }

        return null;
    }
}
