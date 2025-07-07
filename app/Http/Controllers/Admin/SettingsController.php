<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings');
    }

    public function updateGeneral(Request $request)
    {
        // guardar configuración general
    }

    public function updateSecurity(Request $request)
    {
        // guardar configuración de seguridad
    }

    public function toggleMaintenance()
    {
        // Artisan::call('down') o toggle en .env
    }
}

