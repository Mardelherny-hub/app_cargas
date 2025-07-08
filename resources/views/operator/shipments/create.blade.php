<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Nueva Carga') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Crear nueva carga para gestión</p>
            </div>
            <a href="{{ route('operator.shipments.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Volver a Mis Cargas
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <!-- Formulario de Creación -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    <!-- Header del Formulario -->
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Información de la Carga</h3>
                        <p class="mt-1 text-sm text-gray-600">Complete los datos básicos de la nueva carga. Los campos marcados con (*) son obligatorios.</p>
                    </div>

                    <!-- Mensaje de Desarrollo -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Formulario en Desarrollo
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Esta interfaz muestra el diseño final del formulario de creación de cargas. La funcionalidad completa estará disponible cuando se implemente el módulo de cargas.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario (Preview) -->
                    <form action="{{ route('operator.shipments.store') }}" method="POST" class="space-y-6">
                        @csrf

                        <!-- Información General -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Número de Viaje -->
                            <div>
                                <label for="numero_viaje" class="block text-sm font-medium text-gray-700">
                                    Número de Viaje *
                                </label>
                                <input type="text"
                                       name="numero_viaje"
                                       id="numero_viaje"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ej: VJ-2025-001"
                                       disabled>
                                <p class="mt-1 text-sm text-gray-500">Se generará automáticamente si se deja vacío</p>
                            </div>

                            <!-- Fecha de Embarque -->
                            <div>
                                <label for="fecha_embarque" class="block text-sm font-medium text-gray-700">
                                    Fecha de Embarque *
                                </label>
                                <input type="date"
                                       name="fecha_embarque"
                                       id="fecha_embarque"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       disabled>
                            </div>

                            <!-- Puerto de Origen -->
                            <div>
                                <label for="puerto_origen" class="block text-sm font-medium text-gray-700">
                                    Puerto de Origen *
                                </label>
                                <select name="puerto_origen"
                                        id="puerto_origen"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                        disabled>
                                    <option value="">Seleccionar puerto...</option>
                                    <option value="USAHU">Puerto de Asunción (USAHU)</option>
                                    <option value="ARROS">Puerto de Rosario (ARROS)</option>
                                    <option value="ARBUE">Puerto de Buenos Aires (ARBUE)</option>
                                </select>
                            </div>

                            <!-- Puerto de Destino -->
                            <div>
                                <label for="puerto_destino" class="block text-sm font-medium text-gray-700">
                                    Puerto de Destino *
                                </label>
                                <select name="puerto_destino"
                                        id="puerto_destino"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                        disabled>
                                    <option value="">Seleccionar puerto...</option>
                                    <option value="USAHU">Puerto de Asunción (USAHU)</option>
                                    <option value="ARROS">Puerto de Rosario (ARROS)</option>
                                    <option value="ARBUE">Puerto de Buenos Aires (ARBUE)</option>
                                </select>
                            </div>

                        </div>

                        <!-- Información del Transporte -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Información del Transporte</h4>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                <!-- Nave -->
                                <div>
                                    <label for="nave_id" class="block text-sm font-medium text-gray-700">
                                        Embarcación *
                                    </label>
                                    <select name="nave_id"
                                            id="nave_id"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar embarcación...</option>
                                        <option value="1">Barcaza Paraná I</option>
                                        <option value="2">Remolcador Rio Grande</option>
                                        <option value="3">Convoy Hidrovía</option>
                                    </select>
                                </div>

                                <!-- Capitán -->
                                <div>
                                    <label for="capitan_nombre" class="block text-sm font-medium text-gray-700">
                                        Capitán *
                                    </label>
                                    <input type="text"
                                           name="capitan_nombre"
                                           id="capitan_nombre"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Nombre del capitán"
                                           disabled>
                                </div>

                                <!-- Tipo de Transporte -->
                                <div>
                                    <label for="tipo_transporte" class="block text-sm font-medium text-gray-700">
                                        Tipo de Transporte *
                                    </label>
                                    <select name="tipo_transporte"
                                            id="tipo_transporte"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="fluvial">Transporte Fluvial</option>
                                        <option value="maritimo">Transporte Marítimo</option>
                                        <option value="mixto">Transporte Mixto</option>
                                    </select>
                                </div>

                            </div>
                        </div>

                        <!-- Mercadería -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Descripción de la Mercadería</h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Tipo de Mercadería -->
                                <div>
                                    <label for="tipo_mercaderia" class="block text-sm font-medium text-gray-700">
                                        Tipo de Mercadería *
                                    </label>
                                    <select name="tipo_mercaderia"
                                            id="tipo_mercaderia"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar tipo...</option>
                                        <option value="granel">Carga a Granel</option>
                                        <option value="contenedor">Contenedorizada</option>
                                        <option value="general">Carga General</option>
                                        <option value="liquida">Carga Líquida</option>
                                    </select>
                                </div>

                                <!-- Peso Total -->
                                <div>
                                    <label for="peso_total" class="block text-sm font-medium text-gray-700">
                                        Peso Total (Kg) *
                                    </label>
                                    <input type="number"
                                           name="peso_total"
                                           id="peso_total"
                                           step="0.01"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0.00"
                                           disabled>
                                </div>

                            </div>

                            <!-- Descripción Detallada -->
                            <div class="mt-4">
                                <label for="descripcion" class="block text-sm font-medium text-gray-700">
                                    Descripción Detallada
                                </label>
                                <textarea name="descripcion"
                                          id="descripcion"
                                          rows="3"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Descripción detallada de la mercadería..."
                                          disabled></textarea>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500">
                                    * Campos obligatorios
                                </div>
                                <div class="flex space-x-3">
                                    <a href="{{ route('operator.shipments.index') }}"
                                       class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                        Cancelar
                                    </a>
                                    <button type="button"
                                            class="bg-yellow-300 hover:bg-yellow-400 text-yellow-800 px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed"
                                            disabled>
                                        Guardar como Borrador
                                    </button>
                                    <button type="submit"
                                            class="bg-blue-300 hover:bg-blue-400 text-blue-800 px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed"
                                            disabled>
                                        Crear Carga
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>

                    <!-- Información Adicional -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">
                                    Próximas Funcionalidades
                                </h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Validación en tiempo real de puertos y rutas</li>
                                        <li>Integración con sistema de embarcaciones</li>
                                        <li>Cálculo automático de tiempos de viaje</li>
                                        <li>Adjuntar documentos de la carga</li>
                                        <li>Notificaciones automáticas de estado</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
