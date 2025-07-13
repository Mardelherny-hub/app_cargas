<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    /**
     * Mostrar la pantalla de bienvenida del sistema.
     *
     * Si el usuario ya está autenticado, redirigir a su dashboard correspondiente.
     */
    public function index()
    {
        // Si el usuario ya está autenticado, redirigir a su dashboard
        if (Auth::check()) {
            return $this->redirectToDashboard();
        }

        return view('welcome');
    }

    /**
     * Redirigir al dashboard correspondiente según el rol del usuario.
     */
    private function redirectToDashboard()
    {
        $user = Auth::user();

        // Verificar el rol del usuario y redirigir al dashboard correspondiente
        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('company-admin')) {
            // Verificar que tenga empresa asociada
            if ($user->userable_type === 'App\\Models\\Company') {
                return redirect()->route('company.dashboard');
            }

            // Si no tiene empresa, log warning y redirigir al dashboard genérico
            logger()->warning('Company admin without company association in welcome redirect', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return redirect()->route('dashboard');
        }

        if ($user->hasRole('user')) {
            // CORREGIDO: Todos los usuarios operadores van al company dashboard
            if ($user->userable_type === 'App\\Models\\Operator') {
                $operator = $user->userable;

                // Verificar que el operador existe y está activo
                if (!$operator || !$operator->active) {
                    logger()->warning('User with invalid or inactive operator in welcome redirect', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_exists' => !!$operator,
                        'operator_active' => $operator?->active ?? false
                    ]);
                    return redirect()->route('dashboard');
                }

                // CORREGIDO: Todos los operadores deben tener empresa
                if (!$operator->company_id || !$operator->company || !$operator->company->active) {
                    logger()->warning('User with operator without valid company in welcome redirect', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'operator_id' => $operator->id,
                        'company_id' => $operator->company_id,
                        'company_exists' => !!$operator->company,
                        'company_active' => $operator->company?->active ?? false
                    ]);
                    return redirect()->route('dashboard');
                }

                // ÉXITO: Operador con empresa válida
                return redirect()->route('company.dashboard');
            }

            // Si es usuario directo de empresa
            if ($user->userable_type === 'App\\Models\\Company') {
                return redirect()->route('company.dashboard');
            }

            // Si no tiene empresa asociada, es un problema de configuración
            logger()->warning('User without company association in welcome redirect', [
                'user_id' => $user->id,
                'email' => $user->email,
                'userable_type' => $user->userable_type
            ]);

            return redirect()->route('dashboard');
        }

        // Si no tiene rol definido, redirigir al dashboard genérico
        logger()->warning('User without specific role in welcome redirect', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray()
        ]);

        return redirect()->route('dashboard');
    }
}
