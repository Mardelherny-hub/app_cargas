<x-guest-layout>
<div class="min-h-screen flex">
<!-- Panel izquierdo con branding -->
<div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-blue-600 via-blue-700 to-teal-600 relative overflow-hidden">
<!-- Patrón de ondas de fondo -->
<div class="absolute inset-0 opacity-20">
<svg class="w-full h-full" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
<defs>
<pattern id="wave-pattern" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse">
<path d="M0,20 Q10,10 20,20 T40,20" stroke="currentColor" stroke-width="0.5" fill="none"/>
</pattern>
</defs>
<rect width="100%" height="100%" fill="url(#wave-pattern)"/>
</svg>
</div>

<!-- Contenido del panel -->
<div class="relative z-10 flex flex-col justify-center items-center text-white p-12">
<!-- Logo/Icono -->
<div class="mb-8">
<div class="w-24 h-24 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
<svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
<path d="M3 17h18l-2-4H5l-2 4zM12 2L8 6h8l-4-4zm-9 7h18v2H3v-2z"/>
</svg>
</div>
</div>

<!-- Título y descripción -->
<div class="text-center max-w-md">
<h1 class="text-4xl font-bold mb-4">Sistema de Gestión de Cargas</h1>
<p class="text-xl text-blue-100 mb-8">Transporte Fluvial y Marítimo</p>
<div class="space-y-4 text-left">
<div class="flex items-center space-x-3">
<div class="w-2 h-2 bg-cyan-300 rounded-full"></div>
<span class="text-blue-100">Gestión integral de manifiestos</span>
</div>
<div class="flex items-center space-x-3">
<div class="w-2 h-2 bg-cyan-300 rounded-full"></div>
<span class="text-blue-100">Integración con webservices gubernamentales</span>
</div>
<div class="flex items-center space-x-3">
<div class="w-2 h-2 bg-cyan-300 rounded-full"></div>
<span class="text-blue-100">Seguimiento en tiempo real</span>
</div>
<div class="flex items-center space-x-3">
<div class="w-2 h-2 bg-cyan-300 rounded-full"></div>
<span class="text-blue-100">Certificados digitales</span>
</div>
</div>
</div>

<!-- Indicador de seguridad -->
<div class="mt-12 flex items-center space-x-2 text-sm">
<div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
<span class="text-blue-100">Conexión segura</span>
</div>
</div>
</div>

<!-- Panel derecho con formulario -->
<div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-20 xl:px-24">
<div class="mx-auto w-full max-w-sm lg:w-96">
<!-- Header del formulario -->
<div class="text-center mb-8">
<!-- Logo para pantallas pequeñas -->
<div class="lg:hidden mb-6">
<div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-teal-600 rounded-full flex items-center justify-center mx-auto">
<svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
<path d="M3 17h18l-2-4H5l-2 4zM12 2L8 6h8l-4-4zm-9 7h18v2H3v-2z"/>
</svg>
</div>
</div>

<h2 class="text-3xl font-bold text-gray-900">Iniciar Sesión</h2>
<p class="mt-2 text-sm text-gray-600">Acceso al sistema de gestión de cargas</p>
</div>

<!-- Mensaje de acceso restringido -->
<div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
<div class="flex items-center">
<div class="flex-shrink-0">
<svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
<path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
</svg>
</div>
<div class="ml-3">
<p class="text-sm text-blue-800">
<strong>Acceso restringido:</strong> Solo para usuarios autorizados
</p>
</div>
</div>
</div>

<!-- Formulario de login -->
<form method="POST" action="{{ route('login') }}" class="space-y-6">
@csrf

<!-- Email -->
<div>
<x-label for="email" value="{{ __('Email') }}" class="block text-sm font-medium text-gray-700"/>
<div class="mt-1 relative">
<x-input id="email"
class="block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
type="email"
name="email"
:value="old('email')"
placeholder="usuario@empresa.com"
required
autofocus
autocomplete="username" />
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
</svg>
</div>
</div>
<x-input-error for="email" class="mt-2"/>
</div>

<!-- Password -->
<div>
<x-label for="password" value="{{ __('Password') }}" class="block text-sm font-medium text-gray-700"/>
<div class="mt-1 relative">
<x-input id="password"
class="block w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
type="password"
name="password"
placeholder="••••••••"
required
autocomplete="current-password" />
<div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
<svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
</svg>
</div>
</div>
<x-input-error for="password" class="mt-2"/>
</div>

<!-- Remember Me -->
<div class="flex items-center justify-between">
<div class="flex items-center">
<input id="remember_me"
name="remember"
type="checkbox"
class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
<label for="remember_me" class="ml-2 block text-sm text-gray-900">
{{ __('Remember me') }}
</label>
</div>

<!-- Forgot Password (Oculto pero disponible) -->
@if (Route::has('password.request'))
<div class="text-sm">
<a href="{{ route('password.request') }}"
class="font-medium text-blue-600 hover:text-blue-500 text-xs"
style="display: none;"
id="forgot-password-link">
{{ __('Forgot your password?') }}
</a>
<!-- Enlace para mostrar el forgot password -->
<button type="button"
class="font-medium text-gray-400 hover:text-gray-500 text-xs"
onclick="document.getElementById('forgot-password-link').style.display='inline'; this.style.display='none';">
¿Olvidaste tu contraseña?
</button>
</div>
@endif
</div>

<!-- Submit Button -->
<div>
<x-button class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
</svg>
{{ __('Log in') }}
</x-button>
</div>
</form>

<!-- Información adicional -->
<div class="mt-8 text-center">
<div class="relative">
<div class="absolute inset-0 flex items-center">
<div class="w-full border-t border-gray-300"></div>
</div>
<div class="relative flex justify-center text-sm">
<span class="px-2 bg-white text-gray-500">Sistema privado</span>
</div>
</div>

<div class="mt-4 text-xs text-gray-500 space-y-1">
<p>🌐 Argentina • Paraguay</p>
<p>📧 Soporte: info@sistemacargas.com</p>
<p>🔒 Conexión SSL verificada</p>
</div>
</div>

<!-- Volver al home -->
<div class="mt-6 text-center">
<a href="{{ route('welcome') }}"
class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors">
<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
</svg>
Volver al inicio
</a>
</div>
</div>
</div>
</div>
</x-guest-layout>
