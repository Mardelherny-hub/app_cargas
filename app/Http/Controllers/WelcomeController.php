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
            return redirect()->route('company.dashboard');
        }

        if ($user->hasRole('internal-operator')) {
            return redirect()->route('internal.dashboard');
        }

        if ($user->hasRole('external-operator')) {
            return redirect()->route('operator.dashboard');
        }

        // Si no tiene rol definido, redirigir al dashboard genérico
        return redirect()->route('dashboard');
    }
}
