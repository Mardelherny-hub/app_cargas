<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();

        // Actualizar último acceso
        $user->update([
            'last_access' => now(),
        ]);

        // Verificar si el usuario está activo
        if (!$user->active) {
            Auth::logout();
            return redirect()->route('login')->withErrors([
                'email' => 'Su cuenta está desactivada. Contacte al administrador.'
            ]);
        }

        // Si es una relación polimórfica, verificar que la entidad relacionada esté activa
        if ($user->userable) {
            if (method_exists($user->userable, 'active') && !$user->userable->active) {
                Auth::logout();
                return redirect()->route('login')->withErrors([
                    'email' => 'Su perfil está desactivado. Contacte al administrador.'
                ]);
            }
        }

        // Redirigir según el rol del usuario
        $redirectUrl = $this->getRedirectUrl($user);

        // Log del acceso exitoso
        logger()->info('User logged in successfully', [
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

        if ($user->hasRole('internal-operator')) {
            return route('internal.dashboard');
        }

        if ($user->hasRole('external-operator')) {
            // Verificar que tenga empresa asociada
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable->company_id) {
                return route('operator.dashboard');
            }

            // Si no tiene empresa, es un problema de configuración
            logger()->warning('External operator without company association', [
                'user_id' => $user->id,
                'email' => $user->email
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
