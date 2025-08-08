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
                \Log::info('Company admin - Company active status', ['active' => $active]);
                return $active;
            }
            \Log::warning('Company admin - No company associated');
            return false;
        }

        // Users (operadores) deben tener empresa asociada válida  
        if ($user->hasRole('user')) {
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable) {
                $operator = $user->userable;

                // Verificar que el operador está activo
                if (!$operator->active) {
                    \Log::warning('User - Operator inactive', ['operator_id' => $operator->id]);
                    return false;
                }


                // Operadores externos deben tener empresa válida
                if ($operator->type === 'external') {
                    if (!$operator->company_id) {
                        \Log::warning('User - External operator without company', ['operator_id' => $operator->id]);
                        return false;
                    }

                    // Verificar que la empresa existe y está activa
                    $company = $operator->company;
                    if (!$company) {
                        \Log::error('User - External operator company not found', [
                            'operator_id' => $operator->id,
                            'company_id' => $operator->company_id
                        ]);
                        return false;
                    }

                    if (!$company->active) {
                        \Log::warning('User - External operator company inactive', [
                            'operator_id' => $operator->id,
                            'company_id' => $company->id,
                            'company_name' => $company->legal_name
                        ]);
                        return false;
                    }

                    \Log::info('User - External operator access granted', [
                        'operator_id' => $operator->id,
                        'company_id' => $company->id,
                        'company_name' => $company->legal_name
                    ]);
                    return true;
                }

                \Log::warning('User - Unknown operator type', [
                    'operator_id' => $operator->id,
                    'type' => $operator->type
                ]);
                return false;
            }

            // Si es usuario directo de empresa (raro, pero posible)
            if ($user->userable_type === 'App\\Models\\Company' && $user->userable) {
                $active = $user->userable->active;
                \Log::info('User - Direct company association', ['active' => $active]);
                return $active;
            }

            \Log::warning('User - No valid association found', [
                'userable_type' => $user->userable_type,
                'userable_id' => $user->userable_id
            ]);
            return false;
        }

        \Log::warning('User - No recognized role', ['roles' => $user->roles->pluck('name')]);
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
