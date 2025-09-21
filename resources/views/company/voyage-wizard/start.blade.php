<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Nuevo Viaje Completo') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            
            {{-- HEADER PRINCIPAL --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-8 bg-white border-b border-gray-200 text-center">
                    <div class="mx-auto w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h6m-6 4h6m-2 4h2M9 15h2"/>
                        </svg>
                    </div>
                    
                    <h1 class="text-4xl font-bold text-gray-900 mb-4">Crear Viaje Completo</h1>
                    <p class="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        Wizard paso a paso que captura <strong>100% de los datos requeridos por AFIP</strong>.<br>
                        Evita rechazos en webservices mediante validación completa y progresiva.
                    </p>

                    {{-- BENEFICIOS CLAVE --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8 text-left">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Sin Rechazos AFIP</h3>
                                <p class="text-sm text-gray-600">Campos obligatorios validados antes del envío</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Proceso Rápido</h3>
                                <p class="text-sm text-gray-600">Navegación intuitiva y datos pre-cargados</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Datos Completos</h3>
                                <p class="text-sm text-gray-600">Captura todos los campos AFIP obligatorios</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- PASOS DEL WIZARD --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6 text-center">¿Cómo funciona?</h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {{-- PASO 1 --}}
                        <div class="relative">
                            <div class="bg-blue-50 rounded-lg p-6 h-full border border-blue-200">
                                <div class="flex items-center mb-4">
                                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold">1</span>
                                    </div>
                                    <h3 class="ml-3 text-lg font-semibold text-blue-900">Datos del Viaje</h3>
                                </div>
                                <p class="text-blue-800 mb-4">Configure información básica del viaje, embarcación y capitán</p>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Número de viaje y fechas</li>
                                    <li>• Puertos origen y destino</li>
                                    <li>• Embarcación y capitán</li>
                                    <li>• <strong>Campos AFIP:</strong> transporte vacío, convoy</li>
                                </ul>
                            </div>
                            {{-- Flecha conectora --}}
                            <div class="hidden lg:block absolute top-1/2 -right-3 transform -translate-y-1/2">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>

                        {{-- PASO 2 --}}
                        <div class="relative">
                            <div class="bg-green-50 rounded-lg p-6 h-full border border-green-200">
                                <div class="flex items-center mb-4">
                                    <div class="flex-shrink-0 w-10 h-10 bg-green-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold">2</span>
                                    </div>
                                    <h3 class="ml-3 text-lg font-semibold text-green-900">Conocimientos</h3>
                                </div>
                                <p class="text-green-800 mb-4">Agregue todos los conocimientos de embarque del viaje</p>
                                <ul class="text-sm text-green-700 space-y-1">
                                    <li>• Bills of lading dinámicos</li>
                                    <li>• Expedidores y consignatarios</li>
                                    <li>• Descripción de mercadería</li>
                                    <li>• <strong>Campos AFIP:</strong> consolidado, tránsito</li>
                                </ul>
                            </div>
                            {{-- Flecha conectora --}}
                            <div class="hidden lg:block absolute top-1/2 -right-3 transform -translate-y-1/2">
                                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>

                        {{-- PASO 3 --}}
                        <div>
                            <div class="bg-purple-50 rounded-lg p-6 h-full border border-purple-200">
                                <div class="flex items-center mb-4">
                                    <div class="flex-shrink-0 w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold">3</span>
                                    </div>
                                    <h3 class="ml-3 text-lg font-semibold text-purple-900">Mercadería</h3>
                                </div>
                                <p class="text-purple-800 mb-4">Detalle ítems de carga y contenedores por conocimiento</p>
                                <ul class="text-sm text-purple-700 space-y-1">
                                    <li>• Ítems de mercadería detallados</li>
                                    <li>• Contenedores asociados</li>
                                    <li>• Pesos y medidas</li>
                                    <li>• <strong>Campos AFIP:</strong> posición arancelaria, RENAR</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- INFORMACION ADICIONAL --}}
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-lg p-6 mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-yellow-800">Información Importante</h3>
                        <div class="mt-2 text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Guardado automático:</strong> Sus datos se guardan en cada paso</li>
                                <li><strong>Navegación libre:</strong> Puede ir hacia atrás para modificar datos</li>
                                <li><strong>Validación en tiempo real:</strong> Errores detectados inmediatamente</li>
                                <li><strong>Campos obligatorios AFIP:</strong> 14 campos nuevos incluidos automáticamente</li>
                                <li><strong>Vista previa final:</strong> Revise todo antes de crear el viaje</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CAMPOS AFIP IMPLEMENTADOS --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Campos AFIP Obligatorios Incluidos</h3>
                    <p class="text-sm text-gray-600">14 campos adicionales requeridos por AFIP ya integrados</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-3 rounded-lg">
                            <h4 class="font-medium text-blue-900 text-sm">Viajes (2 campos)</h4>
                            <ul class="text-xs text-blue-700 mt-1">
                                <li>• Transporte vacío</li>
                                <li>• Carga a bordo</li>
                            </ul>
                        </div>
                        <div class="bg-green-50 p-3 rounded-lg">
                            <h4 class="font-medium text-green-900 text-sm">Conocimientos (2 campos)</h4>
                            <ul class="text-xs text-green-700 mt-1">
                                <li>• Es consolidado</li>
                                <li>• Es tránsito/transbordo</li>
                            </ul>
                        </div>
                        <div class="bg-purple-50 p-3 rounded-lg">
                            <h4 class="font-medium text-purple-900 text-sm">Mercadería (7 campos)</h4>
                            <ul class="text-xs text-purple-700 mt-1">
                                <li>• Posición arancelaria</li>
                                <li>• Operador logístico seguro</li>
                                <li>• Tránsito monitoreado</li>
                                <li>• RENAR</li>
                                <li>• Forwarder del exterior</li>
                            </ul>
                        </div>
                        <div class="bg-orange-50 p-3 rounded-lg">
                            <h4 class="font-medium text-orange-900 text-sm">Contenedores (1 campo)</h4>
                            <ul class="text-xs text-orange-700 mt-1">
                                <li>• Condición del contenedor</li>
                            </ul>
                        </div>
                        <div class="bg-indigo-50 p-3 rounded-lg">
                            <h4 class="font-medium text-indigo-900 text-sm">Capitanes (1 campo)</h4>
                            <ul class="text-xs text-indigo-700 mt-1">
                                <li>• País del documento</li>
                            </ul>
                        </div>
                        <div class="bg-pink-50 p-3 rounded-lg">
                            <h4 class="font-medium text-pink-900 text-sm">BENEFICIO</h4>
                            <ul class="text-xs text-pink-700 mt-1">
                                <li>• ✅ Sin rechazos AFIP</li>
                                <li>• ✅ Webservices exitosos</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- BOTONES DE ACCION --}}
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0 sm:space-x-4">
                        <div class="text-center sm:text-left">
                            <p class="text-sm text-gray-600">
                                ¿Listo para crear un viaje con <strong>100% compatibilidad AFIP</strong>?
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                Tiempo estimado: 5-10 minutos • Se puede pausar en cualquier momento
                            </p>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-3">
                            {{-- Botón Cancelar --}}
                            <a href="{{ route('company.voyages.index') }}" 
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                                </svg>
                                Volver a Viajes
                            </a>

                            {{-- Botón Crear Viaje Básico --}}
                            <a href="{{ route('company.voyages.create') }}" 
                               class="inline-flex items-center px-4 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Crear Viaje Básico
                            </a>

                            {{-- Botón Principal - Comenzar Wizard --}}
                            <a href="{{ route('voyage-wizard.step1') }}"
                               class="inline-flex items-center px-8 py-3 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Comenzar Wizard Completo
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOOTER CON ESTADISTICAS --}}
            <div class="text-center mt-8">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-2xl mx-auto">
                    <div class="bg-green-100 rounded-lg p-4">
                        <div class="text-2xl font-bold text-green-800">100%</div>
                        <div class="text-sm text-green-600">Compatibilidad AFIP</div>
                    </div>
                    <div class="bg-blue-100 rounded-lg p-4">
                        <div class="text-2xl font-bold text-blue-800">14</div>
                        <div class="text-sm text-blue-600">Campos AFIP Nuevos</div>
                    </div>
                    <div class="bg-purple-100 rounded-lg p-4">
                        <div class="text-2xl font-bold text-purple-800">0</div>
                        <div class="text-sm text-purple-600">Rechazos Esperados</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>