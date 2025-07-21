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

        \Log::info('CompanyAccess Debug', [
            'user_id' => $user->id ?? 'null',
            'email' => $user->email ?? 'null',
            'roles' => $user ? $user->roles->pluck('name') : 'no user',
            'userable_type' => $user->userable_type ?? 'null',
            'userable_id' => $user->userable_id ?? 'null',
        ]);

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
            
            \Log::info('CompanyAccess - Company ID from request', ['company_id' => $companyId]);

            // Si hay un companyId específico en la ruta, verificar acceso
            if ($companyId) {
                if (!$user->belongsToCompany($companyId)) {
                    abort(403, 'No tiene permisos para acceder a esta empresa.');
                }
                return $next($request);
            }

            // Para rutas sin companyId específico
            $hasAccess = $this->userHasCompanyAccess($user);
            \Log::info('CompanyAccess - Has Access Result', ['has_access' => $hasAccess]);
            
            if ($hasAccess) {
                return $next($request);
            }

            // Si el usuario no tiene acceso a ninguna empresa
            abort(403, 'No autorizado: usuario sin empresa asignada.');
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
        \Log::info('userHasCompanyAccess - Checking', [
            'role' => $user->roles->pluck('name'),
            'userable_type' => $user->userable_type,
            'userable_exists' => $user->userable ? 'yes' : 'no',
        ]);

        // Company admin debe tener empresa asociada
        if ($user->hasRole('company-admin')) {
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                $active = $user->userable->active;
                \Log::info('Company active status', ['active' => $active]);
                return $active;
            }
            return false;
        }
        // ... resto del código
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
