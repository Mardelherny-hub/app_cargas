<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Nuevo Viaje') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Planificar nuevo viaje de transporte</p>
            </div>
            <a href="{{ route('operator.trips.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                ← Volver a Mis Viajes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            <!-- Formulario de Creación de Viaje -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    <!-- Header del Formulario -->
                    <div class="border-b border-gray-200 pb-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Planificación del Viaje</h3>
                        <p class="mt-1 text-sm text-gray-600">Configure la ruta, fechas y embarcaciones para el nuevo viaje. Un viaje puede incluir múltiples cargas.</p>
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
                                    Módulo de Viajes en Desarrollo
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Esta interfaz muestra el diseño de planificación de viajes. La funcionalidad completa estará disponible cuando se implemente el módulo de logística.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario (Preview) -->
                    <form action="{{ route('operator.trips.store') }}" method="POST" class="space-y-8">
                        @csrf

                        <!-- Información Básica del Viaje -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                            <!-- Código del Viaje -->
                            <div>
                                <label for="codigo_viaje" class="block text-sm font-medium text-gray-700">
                                    Código del Viaje *
                                </label>
                                <input type="text"
                                       name="codigo_viaje"
                                       id="codigo_viaje"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Ej: TR-2025-001"
                                       disabled>
                                <p class="mt-1 text-sm text-gray-500">Se generará automáticamente</p>
                            </div>

                            <!-- Fecha de Inicio -->
                            <div>
                                <label for="fecha_inicio" class="block text-sm font-medium text-gray-700">
                                    Fecha de Inicio *
                                </label>
                                <input type="datetime-local"
                                       name="fecha_inicio"
                                       id="fecha_inicio"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       disabled>
                            </div>

                            <!-- Fecha Estimada de Llegada -->
                            <div>
                                <label for="fecha_estimada_llegada" class="block text-sm font-medium text-gray-700">
                                    Fecha Estimada de Llegada
                                </label>
                                <input type="datetime-local"
                                       name="fecha_estimada_llegada"
                                       id="fecha_estimada_llegada"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       disabled>
                            </div>

                        </div>

                        <!-- Ruta del Viaje -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Ruta del Viaje</h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Puerto de Partida -->
                                <div>
                                    <label for="puerto_partida" class="block text-sm font-medium text-gray-700">
                                        Puerto de Partida *
                                    </label>
                                    <select name="puerto_partida"
                                            id="puerto_partida"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar puerto...</option>
                                        <option value="USAHU">Puerto de Asunción (USAHU)</option>
                                        <option value="ARROS">Puerto de Rosario (ARROS)</option>
                                        <option value="ARBUE">Puerto de Buenos Aires (ARBUE)</option>
                                        <option value="ARTIG">Puerto de Tigre (ARTIG)</option>
                                    </select>
                                </div>

                                <!-- Puerto de Destino Final -->
                                <div>
                                    <label for="puerto_destino_final" class="block text-sm font-medium text-gray-700">
                                        Puerto de Destino Final *
                                    </label>
                                    <select name="puerto_destino_final"
                                            id="puerto_destino_final"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar puerto...</option>
                                        <option value="USAHU">Puerto de Asunción (USAHU)</option>
                                        <option value="ARROS">Puerto de Rosario (ARROS)</option>
                                        <option value="ARBUE">Puerto de Buenos Aires (ARBUE)</option>
                                        <option value="ARTIG">Puerto de Tigre (ARTIG)</option>
                                    </select>
                                </div>

                            </div>

                            <!-- Puertos Intermedios -->
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Puertos Intermedios (Opcional)
                                </label>
                                <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-4">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">Agregar puertos de escala intermedia</p>
                                        <button type="button" class="mt-2 text-blue-600 hover:text-blue-500 text-sm font-medium" disabled>
                                            + Agregar Puerto Intermedio
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Embarcaciones y Tripulación -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Embarcaciones y Tripulación</h4>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                <!-- Embarcación Principal -->
                                <div>
                                    <label for="embarcacion_principal" class="block text-sm font-medium text-gray-700">
                                        Embarcación Principal *
                                    </label>
                                    <select name="embarcacion_principal"
                                            id="embarcacion_principal"
                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                            disabled>
                                        <option value="">Seleccionar embarcación...</option>
                                        <option value="1">Barcaza Paraná I - Cap: 1500 ton</option>
                                        <option value="2">Remolcador Rio Grande - 2200 HP</option>
                                        <option value="3">Convoy Hidrovía - Cap: 3000 ton</option>
                                        <option value="4">Barcaza María del Carmen - Cap: 2100 ton</option>
                                    </select>
                                </div>

                                <!-- Capitán del Viaje -->
                                <div>
                                    <label for="capitan_viaje" class="block text-sm font-medium text-gray-700">
                                        Capitán del Viaje *
                                    </label>
                                    <input type="text"
                                           name="capitan_viaje"
                                           id="capitan_viaje"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Nombre del capitán"
                                           disabled>
                                </div>

                            </div>

                            <!-- Embarcaciones Adicionales -->
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Embarcaciones Adicionales
                                </label>
                                <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-4">
                                    <div class="text-center">
                                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">Agregar barcazas o remolcadores adicionales</p>
                                        <button type="button" class="mt-2 text-blue-600 hover:text-blue-500 text-sm font-medium" disabled>
                                            + Agregar Embarcación
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cargas Asignadas -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Cargas Asignadas al Viaje</h4>

                            <!-- Cargas Disponibles -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                                <!-- Cargas Disponibles -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Cargas Disponibles
                                    </label>
                                    <div class="border border-gray-300 rounded-md h-48 overflow-y-auto bg-gray-50">
                                        <div class="p-4 text-center text-gray-500">
                                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                            </svg>
                                            <p class="text-sm">No hay cargas disponibles</p>
                                            <p class="text-xs text-gray-400 mt-1">Cree cargas primero para asignarlas</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cargas Seleccionadas -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Cargas Incluidas en el Viaje
                                    </label>
                                    <div class="border border-gray-300 rounded-md h-48 overflow-y-auto bg-gray-50">
                                        <div class="p-4 text-center text-gray-500">
                                            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                            </svg>
                                            <p class="text-sm">No hay cargas seleccionadas</p>
                                            <p class="text-xs text-gray-400 mt-1">Arrastre cargas aquí para incluirlas</p>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <!-- Resumen de Capacidad -->
                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h5 class="text-sm font-medium text-blue-800 mb-2">Resumen de Capacidad</h5>
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p class="text-blue-600">Peso Total:</p>
                                        <p class="font-medium text-blue-800">0 Kg</p>
                                    </div>
                                    <div>
                                        <p class="text-blue-600">Capacidad Embarcación:</p>
                                        <p class="font-medium text-blue-800">- Kg</p>
                                    </div>
                                    <div>
                                        <p class="text-blue-600">Disponible:</p>
                                        <p class="font-medium text-blue-800">- Kg</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Observaciones y Notas</h4>

                            <div>
                                <label for="observaciones" class="block text-sm font-medium text-gray-700">
                                    Observaciones del Viaje
                                </label>
                                <textarea name="observaciones"
                                          id="observaciones"
                                          rows="4"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Instrucciones especiales, condiciones meteorológicas, restricciones, etc..."
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
                                    <a href="{{ route('operator.trips.index') }}"
                                       class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                                        Cancelar
                                    </a>
                                    <button type="button"
                                            class="bg-yellow-300 hover:bg-yellow-400 text-yellow-800 px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed"
                                            disabled>
                                        Guardar Borrador
                                    </button>
                                    <button type="submit"
                                            class="bg-blue-300 hover:bg-blue-400 text-blue-800 px-4 py-2 rounded-md text-sm font-medium cursor-not-allowed"
                                            disabled>
                                        Planificar Viaje
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>

                    <!-- Información Adicional -->
                    <div class="mt-8 bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">
                                    Funcionalidades del Módulo de Viajes
                                </h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Planificación de rutas multi-puerto con optimización</li>
                                        <li>Asignación inteligente de cargas por capacidad</li>
                                        <li>Seguimiento en tiempo real via GPS</li>
                                        <li>Gestión de tripulación y turnos</li>
                                        <li>Alertas automáticas de condiciones meteorológicas</li>
                                        <li>Integración con sistemas de control portuario</li>
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
