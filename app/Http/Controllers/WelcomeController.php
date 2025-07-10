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
            // Verificar que tenga empresa asociada
            if ($user->userable_type === 'App\\Models\\Company') {
                return redirect()->route('company.dashboard');
            }

            // Si es un operador asociado a empresa
            if ($user->userable_type === 'App\\Models\\Operator' && $user->userable->company_id) {
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
