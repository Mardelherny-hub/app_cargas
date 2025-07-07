<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sistema de Gestión de Cargas</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<style>
.wave-pattern {
    background: linear-gradient(45deg, #0ea5e9 0%, #0891b2 50%, #0e7490 100%);
    background-size: 400% 400%;
    animation: wave 8s ease-in-out infinite;
}

@keyframes wave {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.ship-icon {
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
}

.card-hover {
    transition: all 0.3s ease;
}

.card-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>
</head>
<body class="bg-gray-50">
<!-- Header con gradiente marítimo -->
<div class="wave-pattern">
<div class="container mx-auto px-4 py-6">
<div class="flex items-center justify-between">
<div class="flex items-center space-x-3">
<!-- Icono de barco -->
<div class="ship-icon">
<svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
<path d="M3 17h18l-2-4H5l-2 4zM12 2L8 6h8l-4-4zm-9 7h18v2H3v-2z"/>
</svg>
</div>
<div>
<h1 class="text-2xl font-bold text-white">Sistema de Gestión de Cargas</h1>
<p class="text-cyan-100 text-sm">Transporte Fluvial y Marítimo</p>
</div>
</div>
<div class="hidden md:flex items-center space-x-4">
<span class="text-cyan-100 text-sm">Acceso autorizado</span>
<div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
</div>
</div>
</div>
</div>

<!-- Contenido principal -->
<div class="container mx-auto px-4 py-12">
<div class="max-w-4xl mx-auto">
<!-- Mensaje principal -->
<div class="text-center mb-12">
<h2 class="text-3xl font-bold text-gray-900 mb-4">
Bienvenido al Sistema de Gestión de Cargas
</h2>
<p class="text-lg text-gray-600 max-w-3xl mx-auto">
Plataforma integral para la gestión de operaciones de transporte fluvial y marítimo.
Sistema privado con acceso restringido a usuarios autorizados.
</p>
</div>

<!-- Tarjetas de información -->
<div class="grid md:grid-cols-3 gap-8 mb-12">
<!-- Gestión de Cargas -->
<div class="card-hover bg-white rounded-xl shadow-lg p-6 border border-gray-200">
<div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mb-4">
<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
</svg>
</div>
<h3 class="text-xl font-semibold text-gray-900 mb-2">Gestión de Cargas</h3>
<p class="text-gray-600">Control integral de manifiestos, conocimientos y documentación de carga.</p>
</div>

<!-- Seguimiento de Viajes -->
<div class="card-hover bg-white rounded-xl shadow-lg p-6 border border-gray-200">
<div class="flex items-center justify-center w-12 h-12 bg-teal-100 rounded-lg mb-4">
<svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
</svg>
</div>
<h3 class="text-xl font-semibold text-gray-900 mb-2">Seguimiento de Viajes</h3>
<p class="text-gray-600">Monitoreo en tiempo real de rutas fluviales y marítimas.</p>
</div>

<!-- Webservices -->
<div class="card-hover bg-white rounded-xl shadow-lg p-6 border border-gray-200">
<div class="flex items-center justify-center w-12 h-12 bg-cyan-100 rounded-lg mb-4">
<svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
</svg>
</div>
<h3 class="text-xl font-semibold text-gray-900 mb-2">Integración AFIP</h3>
<p class="text-gray-600">Conexión directa con webservices gubernamentales y aduaneros.</p>
</div>
</div>

<!-- Sección de acceso -->
<div class="bg-white rounded-xl shadow-lg p-8 border border-gray-200">
<div class="text-center">
<div class="flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mx-auto mb-4">
<svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
</svg>
</div>
<h3 class="text-2xl font-bold text-gray-900 mb-4">Acceso al Sistema</h3>
<p class="text-gray-600 mb-6">
El acceso al sistema está restringido a usuarios autorizados.
Si tiene credenciales válidas, puede ingresar al sistema.
</p>
<div class="flex flex-col sm:flex-row gap-4 justify-center">
<a href="{{ route('login') }}"
class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
</svg>
Iniciar Sesión
</a>
<a href="#contacto"
class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
</svg>
Contacto
</a>
</div>
</div>
</div>
</div>
</div>

<!-- Footer -->
<footer class="bg-gray-800 text-white py-8 mt-16">
<div class="container mx-auto px-4">
<div class="grid md:grid-cols-3 gap-8">
<!-- Información del sistema -->
<div>
<h4 class="text-lg font-semibold mb-4">Sistema de Gestión de Cargas</h4>
<p class="text-gray-300 text-sm">
Plataforma especializada en la gestión de operaciones de transporte fluvial y marítimo,
con integración directa a organismos gubernamentales.
</p>
</div>

<!-- Características -->
<div>
<h4 class="text-lg font-semibold mb-4">Características</h4>
<ul class="text-gray-300 text-sm space-y-2">
<li>• Gestión integral de manifiestos</li>
<li>• Integración con AFIP</li>
<li>• Seguimiento en tiempo real</li>
<li>• Certificados digitales</li>
<li>• Reportes personalizados</li>
</ul>
</div>

<!-- Contacto -->
<div id="contacto">
<h4 class="text-lg font-semibold mb-4">Contacto</h4>
<div class="text-gray-300 text-sm space-y-2">
<p>📧 info@sistemacargas.com</p>
<p>📞 +54 11 1234-5678</p>
<p>🌐 Argentina • Paraguay</p>
<p class="mt-4 text-xs text-gray-400">
Sistema privado - Solo usuarios autorizados
</p>
</div>
</div>
</div>

<div class="border-t border-gray-700 mt-8 pt-4 text-center">
<p class="text-gray-400 text-sm">
© {{ date('Y') }} Sistema de Gestión de Cargas. Todos los derechos reservados.
</p>
</div>
</div>
</footer>
</body>
</html>
