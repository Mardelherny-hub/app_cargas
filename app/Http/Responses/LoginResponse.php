<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = auth()->user();
        $redirectUrl = $this->getRedirectUrl($user);

        // Log del acceso para auditoría
        logger('User login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->roles->first()?->name ?? 'no-role',
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'redirect_to' => $redirectUrl
        ]);

        return redirect()->intended($redirectUrl);
    }

    /**
     * Determinar la URL de redirección según el rol del usuario.
     *
     * @param  \App\Models\User  $user
     * @return string
     */
    private function getRedirectUrl($user): string
    {
        // Verificar roles en orden de prioridad
        if ($user->hasRole('super-admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('company-admin')) {
            // Verificar que tenga empresa asociada
            if ($user->userable_type === 'App\\Models\\Company') {
                return route('company.dashboard');
            }

            // Si no tiene empresa, es un problema de configuración
            logger()->warning('Company admin without company association', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return route('dashboard');
        }

        if ($user->hasRole('user')) {
            // CORRECCIÓN: Verificación mejorada para usuarios operadores
            if ($user->userable_type === 'App\\Models\\Operator') {
                $operator = $user->userable;

                // Verificar que el operador existe
                if (!$operator) {
                    logger()->error('User with operator type but no operator found', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'userable_id' => $user->userable_id
                    ]);
                    return route('dashboard');
                }

                // Verificar que el operador está activo
                if (!$operator->active) {
                    logger()->warning('User with inactive operator trying to login', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_id' => $operator->id
                    ]);
                    return route('dashboard');
                }

                // CORRECCIÓN: Los operadores externos deben tener company_id
                if ($operator->type === 'external' && $operator->company_id) {
                    return route('company.dashboard');
                }

                // CORRECCIÓN: Los operadores internos también pueden acceder al dashboard de company
                // si tienen una empresa asociada (aunque company_id sea null)
                if ($operator->type === 'internal') {
                    // Los operadores internos pueden trabajar con múltiples empresas
                    // pero para el dashboard necesitan al menos una empresa disponible
                    $availableCompanies = \App\Models\Company::where('active', true)->count();

                    if ($availableCompanies > 0) {
                        return route('company.dashboard');
                    }
                }

                // Si el operador no tiene empresa asociada válida
                logger()->warning('Operator without valid company association', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'operator_type' => $operator->type,
                    'company_id' => $operator->company_id,
                    'operator_active' => $operator->active
                ]);

                return route('dashboard');
            }

            // Si es usuario directo de empresa (poco común pero posible)
            if ($user->userable_type === 'App\\Models\\Company') {
                return route('company.dashboard');
            }

            // Si no tiene empresa asociada, es un problema de configuración
            logger()->warning('User without company association', [
                'user_id' => $user->id,
                'email' => $user->email,
                'userable_type' => $user->userable_type
            ]);

            return route('dashboard');
        }

        // Si no tiene rol específico, redirigir al dashboard genérico
        logger()->warning('User login without specific role', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray()
        ]);

        return route('dashboard');
    }
}
