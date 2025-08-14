<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📋 Cómo Crear un Manifiesto
            </h2>
            <nav class="text-sm">
                <a href="{{ route('company.manifests.index') }}" class="text-gray-500 hover:text-gray-700">
                    Manifiestos
                </a>
                <span class="text-gray-400 mx-2">/</span>
                <span class="text-gray-900">Guía de Creación</span>
            </nav>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Introducción -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white mb-8">
                <h3 class="text-2xl font-bold mb-3">🚢 Guía Completa para Crear Manifiestos</h3>
                <p class="text-blue-100 text-lg">
                    Un manifiesto se construye paso a paso. Siga esta guía para crear manifiestos completos y correctos para las autoridades aduaneras.
                </p>
            </div>

            <!-- Proceso paso a paso -->
            <div class="space-y-6">
                
                <!-- Paso 1: Crear Viaje -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-blue-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    1
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">🗺️ Crear el Viaje Base</h4>
                                <p class="text-gray-600 mb-4">
                                    Defina la ruta, fechas, embarcación principal y capitán. Este es el contenedor principal que agrupará todas las cargas.
                                </p>
                                
                                <div class="bg-gray-50 rounded-md p-4 mb-4">
                                    <h5 class="font-medium text-gray-800 mb-2">Información requerida:</h5>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• <strong>Número de viaje:</strong> Identificador único (ej: V2025-001)</li>
                                        <li>• <strong>Ruta:</strong> Puerto origen → Puerto destino</li>
                                        <li>• <strong>Fechas:</strong> Salida y llegada estimada</li>
                                        <li>• <strong>Embarcación:</strong> Vessel principal del viaje</li>
                                        <li>• <strong>Capitán:</strong> Responsable de la navegación</li>
                                    </ul>
                                </div>

                                <a href="{{ route('company.voyages.create') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Crear Nuevo Viaje
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 2: Agregar Shipments -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-green-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-bold">
                                    2
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">🚛 Agregar Embarques (Shipments)</h4>
                                <p class="text-gray-600 mb-4">
                                    Cada shipment representa un embarque específico dentro del viaje. Puede ser la misma embarcación u otras adicionales en caso de convoy.
                                </p>
                                
                                <div class="bg-gray-50 rounded-md p-4 mb-4">
                                    <h5 class="font-medium text-gray-800 mb-2">Configuración del shipment:</h5>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• <strong>Embarcación:</strong> Vessel que transporta las cargas</li>
                                        <li>• <strong>Secuencia:</strong> Orden dentro del viaje</li>
                                        <li>• <strong>Capacidades:</strong> Tonelaje y contenedores disponibles</li>
                                        <li>• <strong>Rol:</strong> Principal o secundaria en convoy</li>
                                    </ul>
                                </div>

                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                                    <div class="flex">
                                        <svg class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-yellow-700">
                                            <strong>Importante:</strong> Primero debe crear el viaje, luego acceda a él para agregar shipments.
                                        </span>
                                    </div>
                                </div>

                                <a href="{{ route('company.voyages.index') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    Ver Mis Viajes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Agregar Primer Ítem de Carga -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-orange-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center text-white font-bold">
                                    3
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">📦 Agregar Primer Ítem de Carga</h4>
                                <p class="text-gray-600 mb-4">
                                    Desde el shipment, agregue el primer ítem de mercadería. <strong>Esto generará automáticamente el Conocimiento de Embarque (Bill of Lading)</strong> donde se incorporarán todos los ítems posteriores.
                                </p>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-4">
                                    <div class="flex">
                                        <svg class="h-5 w-5 text-blue-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-blue-700">
                                            <strong>Flujo:</strong> Viaje → Shipment → "Add Cargo Item" → Se crea automáticamente el Bill of Lading
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-md p-4 mb-4">
                                    <h5 class="font-medium text-gray-800 mb-2">Datos del primer ítem:</h5>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• <strong>Descripción:</strong> Tipo y características de la mercadería</li>
                                        <li>• <strong>Cantidades:</strong> Peso bruto/neto, volumen, bultos</li>
                                        <li>• <strong>Clasificación:</strong> Código HS, país de origen (ISO 3 letras)</li>
                                        <li>• <strong>Embalaje:</strong> Tipo de contenedor o empaque</li>
                                        <li>• <strong>Marcas:</strong> Identificación y manejo especial</li>
                                    </ul>
                                </div>

                                <a href="{{ route('company.shipments.index') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 focus:bg-orange-700 active:bg-orange-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    Ver Mis Shipments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 4: Agregar Ítems Adicionales -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-teal-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-teal-500 rounded-full flex items-center justify-center text-white font-bold">
                                    4
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">📋 Agregar Ítems Adicionales</h4>
                                <p class="text-gray-600 mb-4">
                                    Continue agregando los demás ítems de mercadería al mismo shipment. <strong>Estos se incorporarán automáticamente al Bill of Lading ya creado.</strong>
                                </p>
                                
                                <div class="bg-green-50 border border-green-200 rounded-md p-3 mb-4">
                                    <div class="flex">
                                        <svg class="h-5 w-5 text-green-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-green-700">
                                            <strong>Automático:</strong> Los ítems 2, 3, 4... se agregan al mismo Conocimiento de Embarque
                                        </span>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded-md p-4 mb-4">
                                    <h5 class="font-medium text-gray-800 mb-2">Para cada ítem adicional:</h5>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• <strong>Número de línea:</strong> Se asigna automáticamente (2, 3, 4...)</li>
                                        <li>• <strong>Mismo proceso:</strong> Completar todos los datos de mercadería</li>
                                        <li>• <strong>Verificar totales:</strong> Peso y volumen acumulados</li>
                                    </ul>
                                </div>

                                <a href="{{ route('company.bills-of-lading.index') }}" 
                                   class="inline-flex items-center px-4 py-2 bg-teal-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-teal-700 focus:bg-teal-700 active:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Ver Bills of Lading
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 5: Gestión de Estados -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-purple-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                    5
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">⚙️ Gestión de Estados</h4>
                                <p class="text-gray-600 mb-4">
                                    Una vez completadas todas las cargas, gestione los estados del viaje, shipments y conocimientos según el progreso real de la operación.
                                </p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div class="bg-gray-50 rounded-md p-3">
                                        <h5 class="font-medium text-gray-800 mb-2">Estados de Viaje/Shipment:</h5>
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            <li>• <strong>Planning:</strong> En planificación</li>
                                            <li>• <strong>Loading:</strong> Cargando mercadería</li>
                                            <li>• <strong>In Transit:</strong> En viaje</li>
                                            <li>• <strong>Completed:</strong> Finalizado</li>
                                        </ul>
                                    </div>
                                    <div class="bg-gray-50 rounded-md p-3">
                                        <h5 class="font-medium text-gray-800 mb-2">Estados de Conocimiento:</h5>
                                        <ul class="text-sm text-gray-600 space-y-1">
                                            <li>• <strong>Draft:</strong> Borrador</li>
                                            <li>• <strong>Issued:</strong> Emitido</li>
                                            <li>• <strong>Verified:</strong> Verificado</li>
                                            <li>• <strong>Completed:</strong> Completado</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                                    <div class="flex">
                                        <svg class="h-5 w-5 text-yellow-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-yellow-700">
                                            <strong>Importante:</strong> Los estados deben actualizarse según el progreso real para mantener trazabilidad
                                        </span>
                                    </div>
                                </div>

                                <div class="flex space-x-3">
                                    <a href="{{ route('company.voyages.index') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Gestionar Estados
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 6: Generar Manifiestos Finales -->
                <div class="bg-white rounded-lg shadow-md border-l-4 border-indigo-500">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-indigo-500 rounded-full flex items-center justify-center text-white font-bold">
                                    6
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">📄 Generar Manifiestos Finales</h4>
                                <p class="text-gray-600 mb-4">
                                    Con todos los ítems cargados y estados actualizados, genere los manifiestos oficiales para presentar a las autoridades aduaneras.
                                </p>
                                
                                <div class="bg-gray-50 rounded-md p-4 mb-4">
                                    <h5 class="font-medium text-gray-800 mb-2">Documentos disponibles:</h5>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• <strong>Manifiesto de Carga:</strong> Listado completo de mercaderías por viaje</li>
                                        <li>• <strong>Bills of Lading:</strong> Conocimientos individuales por shipment</li>
                                        <li>• <strong>Reportes Aduaneros:</strong> Formatos específicos por país</li>
                                        <li>• <strong>Resúmenes Ejecutivos:</strong> Totales y estadísticas del viaje</li>
                                    </ul>
                                </div>

                                <div class="flex space-x-3">
                                    <a href="{{ route('company.manifests.index') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Ver Manifiestos
                                    </a>
                                    
                                    <a href="{{ route('company.reports.index') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                        Ver Reportes
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div></div>
                </div>
            </div>

            <!-- Flujo visual -->
            <div class="mt-8 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🔄 Flujo Completo del Sistema</h3>
                <div class="flex flex-wrap items-center justify-center space-x-4 text-sm">
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-bold">1</span>
                        <span class="font-medium">Viaje</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center text-white text-xs font-bold">2</span>
                        <span class="font-medium">Shipment</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-orange-500 rounded-full flex items-center justify-center text-white text-xs font-bold">3</span>
                        <span class="font-medium">1° Ítem</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-yellow-100 rounded-lg px-3 py-2 shadow-sm border-2 border-yellow-300">
                        <span class="w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center text-white text-xs">✨</span>
                        <span class="font-medium text-yellow-800">Bill of Lading AUTO</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-teal-500 rounded-full flex items-center justify-center text-white text-xs font-bold">4</span>
                        <span class="font-medium">Más Ítems</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-purple-500 rounded-full flex items-center justify-center text-white text-xs font-bold">5</span>
                        <span class="font-medium">Estados</span>
                    </div>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    <div class="flex items-center space-x-2 bg-white rounded-lg px-3 py-2 shadow-sm">
                        <span class="w-6 h-6 bg-indigo-500 rounded-full flex items-center justify-center text-white text-xs font-bold">6</span>
                        <span class="font-medium">Manifiestos</span>
                    </div>
                </div>
            </div>
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">💡 Consejos Importantes</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-800 mb-2">✅ Buenas Prácticas</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• Use números de viaje consistentes (ej: V2025-001)</li>
                            <li>• Verifique los puertos antes de crear el viaje</li>
                            <li>• Complete todos los datos de peso y volumen</li>
                            <li>• Revise los códigos HS de las mercaderías</li>
                            <li>• Mantenga copias de la documentación</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-800 mb-2">⚠️ Errores Comunes</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• No verificar las capacidades de las embarcaciones</li>
                            <li>• Datos incompletos en las cargas</li>
                            <li>• Puertos de origen y destino iguales</li>
                            <li>• Fechas de salida en el pasado</li>
                            <li>• Códigos de país incorrectos (usar ISO de 3 letras)</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="mt-8 bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🚀 Accesos Rápidos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route('company.voyages.create') }}" 
                       class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:shadow-md transition-all duration-200">
                        <div class="text-center">
                            <div class="text-2xl mb-2">🗺️</div>
                            <div class="font-medium text-gray-900">Nuevo Viaje</div>
                            <div class="text-sm text-gray-500">Crear viaje base</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('company.voyages.index') }}" 
                       class="block p-4 border border-gray-200 rounded-lg hover:border-green-300 hover:shadow-md transition-all duration-200">
                        <div class="text-center">
                            <div class="text-2xl mb-2">🚛</div>
                            <div class="font-medium text-gray-900">Mis Viajes</div>
                            <div class="text-sm text-gray-500">Gestionar shipments</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('company.shipments.index') }}" 
                       class="block p-4 border border-gray-200 rounded-lg hover:border-orange-300 hover:shadow-md transition-all duration-200">
                        <div class="text-center">
                            <div class="text-2xl mb-2">📦</div>
                            <div class="font-medium text-gray-900">Shipments</div>
                            <div class="text-sm text-gray-500">Agregar ítems de carga</div>
                        </div>
                    </a>
                    
                    <a href="{{ route('company.manifests.index') }}" 
                       class="block p-4 border border-gray-200 rounded-lg hover:border-indigo-300 hover:shadow-md transition-all duration-200">
                        <div class="text-center">
                            <div class="text-2xl mb-2">📋</div>
                            <div class="font-medium text-gray-900">Manifiestos</div>
                            <div class="text-sm text-gray-500">Ver documentos</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>