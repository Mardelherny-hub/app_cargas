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
            // CORREGIDO: Todos los usuarios operadores van al company dashboard
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

                // CORREGIDO: Todos los operadores deben tener empresa asociada
                if (!$operator->company_id) {
                    logger()->error('Operator without company association', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_id' => $operator->id,
                        'operator_type' => $operator->type
                    ]);
                    return route('dashboard');
                }

                // Verificar que la empresa existe y está activa
                $company = $operator->company;
                if (!$company) {
                    logger()->error('Operator with invalid company_id', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_id' => $operator->id,
                        'company_id' => $operator->company_id
                    ]);
                    return route('dashboard');
                }

                if (!$company->active) {
                    logger()->warning('Operator with inactive company trying to login', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_id' => $operator->id,
                        'company_id' => $company->id,
                        'company_name' => $company->legal_name
                    ]);
                    return route('dashboard');
                }

                // ÉXITO: Operador con empresa válida → company dashboard
                return route('company.dashboard');
            }

            // Si es usuario directo de empresa (company-admin con rol user)
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
