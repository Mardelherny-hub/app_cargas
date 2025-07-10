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

            if ($companyId && !$user->belongsToCompany($companyId)) {
                abort(403, 'No tiene permisos para acceder a esta empresa.');
            }

            return $next($request);
        }

        // If user has no recognized role, deny access
        abort(403, 'No tiene permisos para acceder a esta sección.');
    }

    /**
     * Get company ID from request
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
