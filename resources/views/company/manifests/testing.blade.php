{{-- resources/views/company/manifests/testing.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    🧪 Testing de Envíos a Aduanas
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Pruebe la validación y conectividad antes de envíos reales
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('company.manifests.customs.index') }}" 
                   class="text-gray-600 hover:text-gray-900 text-sm">
                    🏛️ Ir a Envíos Reales
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Alertas de sesión -->
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if (session('info'))
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded" role="alert">
                    <span class="block sm:inline">{{ session('info') }}</span>
                </div>
            @endif

            <!-- Estadísticas de Testing -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        📊 Estadísticas de Testing
                    </h3>
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="bg-blue-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-blue-600">🚢 Viajes Disponibles</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-900">{{ $stats['total_voyages'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-green-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-green-600">✅ Tests Hoy</dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-900">{{ $stats['tested_today'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-yellow-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-yellow-600">⚠️ Con Advertencias</dt>
                            <dd class="mt-1 text-2xl font-semibold text-yellow-900">{{ $stats['failed_tests'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-purple-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-purple-600">📈 Tasa de Éxito</dt>
                            <dd class="mt-1 text-2xl font-semibold text-purple-900">{{ number_format($stats['success_rate'] ?? 0, 1) }}%</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Información de Testing -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        🔧 Tipos de Pruebas Disponibles
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Validación de Datos -->
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                            <h4 class="font-medium text-blue-900 mb-2">📋 Validación de Datos</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• Campos obligatorios completos</li>
                                <li>• Formato de datos correcto</li>
                                <li>• Relaciones válidas</li>
                            </ul>
                        </div>

                        <!-- Testing de Conectividad -->
                        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                            <h4 class="font-medium text-green-900 mb-2">🌐 Conectividad</h4>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li>• Conexión con webservices</li>
                                <li>• Certificados válidos</li>
                                <li>• URLs operativas</li>
                            </ul>
                        </div>
                        
                        <!-- Simulación de Envío -->
                        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                            <h4 class="font-medium text-yellow-900 mb-2">🎭 Simulación</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Generación de XML</li>
                                <li>• Envío simulado</li>
                                <li>• Respuesta de testing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        🔍 Filtros de Búsqueda
                    </h3>
                    
                    <form method="GET" action="{{ route('company.manifests.testing.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Número de Viaje -->
                        <div>
                            <label for="voyage_number" class="block text-sm font-medium text-gray-700">Número de Viaje</label>
                            <input type="text" 
                                   name="voyage_number" 
                                   id="voyage_number"
                                   value="{{ request('voyage_number') }}"
                                   placeholder="Ej: V022NB"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>

                        <!-- Vessel -->
                        <div>
                            <label for="vessel_id" class="block text-sm font-medium text-gray-700">Embarcación</label>
                            <select name="vessel_id" id="vessel_id" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Todas las embarcaciones</option>
                                @foreach($filters['vessels'] as $id => $name)
                                    <option value="{{ $id }}" {{ request('vessel_id') == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                            <select name="status" id="status" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Todos los estados</option>
                                @foreach($filters['statuses'] as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Botones -->
                        <div class="flex items-end space-x-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                🔍 Filtrar
                            </button>
                            @if(request()->hasAny(['voyage_number', 'vessel_id', 'status']))
                                <a href="{{ route('company.manifests.testing.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                    🗑️ Limpiar
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Listado de Viajes para Testing -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                🚢 Viajes Disponibles para Testing
                            </h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Seleccione viajes para ejecutar pruebas de validación y conectividad
                            </p>
                        </div>
                        
                        <!-- Botón de testing masivo -->
                        <button type="button" id="bulk-test-btn" 
                                class="hidden inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                            🧪 Testing Masivo
                        </button>
                    </div>
                </div>

                <!-- Tabla de Viajes -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Viaje
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Embarcación
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cargas
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($voyages as $voyage)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" name="voyage_ids[]" value="{{ $voyage->id }}" 
                                           class="voyage-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $voyage->voyage_number }}</div>
                                    <div class="text-sm text-gray-500">
                                        @if($voyage->departure_date)
                                            {{ \Carbon\Carbon::parse($voyage->departure_date)->format('d/m/Y') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $voyage->vessel->vessel_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $voyage->vessel->vessel_code ?? '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $voyage->shipments_count ?? $voyage->shipments()->count() }} cargas
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $voyage->containers_count ?? $voyage->containers()->count() }} contenedores
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @switch($voyage->status)
                                        @case('completed')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                ✅ Completado
                                            </span>
                                            @break
                                        @case('in_progress')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                🚢 En Progreso
                                            </span>
                                            @break
                                        @case('approved')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                ✅ Aprobado
                                            </span>
                                            @break
                                        @default
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ ucfirst($voyage->status) }}
                                            </span>
                                    @endswitch
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <!-- Botón de testing individual -->
                                    <button type="button" 
                                            onclick="openTestModal({{ $voyage->id }}, '{{ $voyage->voyage_number }}')"
                                            class="inline-flex items-center px-3 py-1 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
                                        🧪 Test
                                    </button>
                                    
                                    <!-- Ver detalles -->
                                    <a href="{{ route('company.manifests.show', $voyage->id) }}" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        👁️ Ver
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    <div class="py-8">
                                        <h3 class="text-lg font-medium text-gray-900">No hay viajes disponibles para testing</h3>
                                        <p class="text-sm text-gray-600 mt-2">
                                            Los viajes deben estar en estado "completado", "en progreso" o "aprobado" para poder realizar testing.
                                        </p>
                                        <div class="mt-6">
                                            <a href="{{ route('company.manifests.index') }}" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                📋 Ver Manifiestos
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($voyages->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $voyages->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Modal de Testing Individual -->
    <div id="test-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">🧪 Ejecutar Testing</h3>
                    <button type="button" onclick="closeTestModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="test-form" onsubmit="submitTest(event)">
                    <input type="hidden" id="test-voyage-id" name="voyage_id">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Viaje</label>
                        <div id="test-voyage-info" class="text-sm text-gray-600 p-2 bg-gray-50 rounded"></div>
                    </div>

                    <div class="mb-4">
                        <label for="test-webservice-type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Webservice</label>
                        <select name="webservice_type" id="test-webservice-type" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            @foreach($filters['webservice_types'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="test-environment" class="block text-sm font-medium text-gray-700 mb-1">Ambiente</label>
                        <select name="environment" id="test-environment" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="testing">🧪 Testing</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Solo ambiente testing disponible para pruebas</p>
                    </div>

                    <div class="mb-6">
                        <label for="test-type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Prueba</label>
                        <select name="test_type" id="test-type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="full">🔍 Completa (Validación + Conectividad + Simulación)</option>
                            <option value="basic">⚡ Básica (Solo Validación)</option>
                            <option value="connectivity_only">🌐 Solo Conectividad</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeTestModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancelar
                        </button>
                        <button type="submit" id="test-submit-btn"
                                class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            🧪 Ejecutar Testing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Testing Masivo -->
    <div id="bulk-test-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">🧪 Testing Masivo</h3>
                    <button type="button" onclick="closeBulkTestModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('company.manifests.testing.testBatch') }}" id="bulk-test-form">
                    @csrf
                    <input type="hidden" name="voyage_ids" id="bulk-voyage-ids">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Viajes Seleccionados</label>
                        <div id="bulk-voyage-count" class="text-sm text-gray-600 p-2 bg-gray-50 rounded">
                            Ningún viaje seleccionado
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="bulk-webservice-type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Webservice</label>
                        <select name="webservice_type" id="bulk-webservice-type" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            @foreach($filters['webservice_types'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="bulk-environment" class="block text-sm font-medium text-gray-700 mb-1">Ambiente</label>
                        <select name="environment" id="bulk-environment" required class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="testing">🧪 Testing</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeBulkTestModal()" 
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancelar
                        </button>
                        <button type="submit" id="bulk-test-submit-btn"
                                class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            🧪 Ejecutar Testing Masivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Resultados de Testing -->
    <div id="results-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">📊 Resultados de Testing</h3>
                    <button type="button" onclick="closeResultsModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="results-content" class="max-h-96 overflow-y-auto">
                    <!-- Los resultados se cargan dinámicamente aquí -->
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="closeResultsModal()" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript para funcionalidad de testing -->
    <script>
        // Variables globales
        let selectedVoyages = [];

        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updateBulkTestButton();
            setupCheckboxListeners();
        });

        // ========================================
        // MANEJO DE CHECKBOXES Y SELECCIÓN MASIVA
        // ========================================

        function setupCheckboxListeners() {
            // Checkbox "Seleccionar todo"
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const voyageCheckboxes = document.querySelectorAll('.voyage-checkbox');
                    voyageCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateSelectedVoyages();
                });
            }

            // Checkboxes individuales
            const voyageCheckboxes = document.querySelectorAll('.voyage-checkbox');
            voyageCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedVoyages);
            });
        }

        function updateSelectedVoyages() {
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            selectedVoyages = Array.from(checkedBoxes).map(cb => cb.value);
            
            updateBulkTestButton();
            updateSelectAllCheckbox();
        }

        function updateBulkTestButton() {
            const bulkTestBtn = document.getElementById('bulk-test-btn');
            if (selectedVoyages.length > 0) {
                bulkTestBtn.classList.remove('hidden');
                bulkTestBtn.textContent = `🧪 Testing Masivo (${selectedVoyages.length})`;
            } else {
                bulkTestBtn.classList.add('hidden');
            }
        }

        function updateSelectAllCheckbox() {
            const selectAllCheckbox = document.getElementById('select-all');
            const voyageCheckboxes = document.querySelectorAll('.voyage-checkbox');
            const checkedBoxes = document.querySelectorAll('.voyage-checkbox:checked');
            
            if (checkedBoxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedBoxes.length === voyageCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }

        // ========================================
        // MODAL DE TESTING INDIVIDUAL
        // ========================================

        function openTestModal(voyageId, voyageNumber) {
            document.getElementById('test-voyage-id').value = voyageId;
            document.getElementById('test-voyage-info').textContent = `Viaje: ${voyageNumber}`;
            document.getElementById('test-modal').classList.remove('hidden');
        }

        function closeTestModal() {
            document.getElementById('test-modal').classList.add('hidden');
            document.getElementById('test-form').reset();
        }

        function submitTest(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const voyageId = formData.get('voyage_id');
            const submitBtn = document.getElementById('test-submit-btn');
            
            // Mostrar loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Ejecutando...';
            
            // Construir URL para la request
            const url = `{{ route('company.manifests.testing.test', ':voyage_id') }}`.replace(':voyage_id', voyageId);
            
            // Enviar request AJAX
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    webservice_type: formData.get('webservice_type'),
                    environment: formData.get('environment'),
                    test_type: formData.get('test_type')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTestResults(data.results);
                    showAlert('success', 'Testing completado correctamente');
                } else {
                    showAlert('error', data.message || 'Error ejecutando testing');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error de comunicación con el servidor');
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.disabled = false;
                submitBtn.innerHTML = '🧪 Ejecutar Testing';
                closeTestModal();
            });
        }

        // ========================================
        // MODAL DE TESTING MASIVO
        // ========================================

        function openBulkTestModal() {
            if (selectedVoyages.length === 0) {
                showAlert('warning', 'Debe seleccionar al menos un viaje');
                return;
            }

            document.getElementById('bulk-voyage-ids').value = selectedVoyages.join(',');
            document.getElementById('bulk-voyage-count').textContent = 
                `${selectedVoyages.length} viaje(s) seleccionado(s)`;
            document.getElementById('bulk-test-modal').classList.remove('hidden');
        }

        function closeBulkTestModal() {
            document.getElementById('bulk-test-modal').classList.add('hidden');
            document.getElementById('bulk-test-form').reset();
        }

        // Event listener para el botón de testing masivo
        document.addEventListener('DOMContentLoaded', function() {
            const bulkTestBtn = document.getElementById('bulk-test-btn');
            if (bulkTestBtn) {
                bulkTestBtn.addEventListener('click', openBulkTestModal);
            }
        });

        // ========================================
        // MODAL DE RESULTADOS
        // ========================================

        function showTestResults(results) {
            const resultsContent = document.getElementById('results-content');
            resultsContent.innerHTML = generateResultsHTML(results);
            document.getElementById('results-modal').classList.remove('hidden');
        }

        function closeResultsModal() {
            document.getElementById('results-modal').classList.add('hidden');
        }

        function generateResultsHTML(results) {
            let html = '';
            
            // Resumen general
            if (results.summary) {
                const summary = results.summary;
                const statusColor = summary.status === 'success' ? 'green' : 
                                  summary.status === 'warning' ? 'yellow' : 'red';
                
                html += `
                    <div class="mb-6">
                        <h4 class="text-lg font-medium text-gray-900 mb-3">📊 Resumen General</h4>
                        <div class="bg-${statusColor}-50 border border-${statusColor}-200 rounded-lg p-4">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-${statusColor}-800">${summary.total_checks || 0}</div>
                                    <div class="text-sm text-${statusColor}-600">Total Checks</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-green-800">${summary.passed_checks || 0}</div>
                                    <div class="text-sm text-green-600">Exitosos</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-red-800">${summary.total_errors || 0}</div>
                                    <div class="text-sm text-red-600">Errores</div>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-yellow-800">${summary.total_warnings || 0}</div>
                                    <div class="text-sm text-yellow-600">Advertencias</div>
                                </div>
                            </div>
                            <div class="mt-4 text-center">
                                <div class="text-lg font-medium text-${statusColor}-800">
                                    Tasa de Éxito: ${summary.success_rate || 0}%
                                </div>
                                <div class="text-sm text-${statusColor}-600 mt-1">
                                    ${summary.ready_for_production ? '✅ Listo para producción' : '⚠️ Requiere correcciones'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Detalles por sección
            const sections = [
                { key: 'data_validation', title: '📋 Validación de Datos', icon: '📋' },
                { key: 'certificate_validation', title: '🔐 Validación de Certificados', icon: '🔐' },
                { key: 'connectivity_test', title: '🌐 Test de Conectividad', icon: '🌐' },
                { key: 'xml_validation', title: '📄 Validación de XML', icon: '📄' },
                { key: 'simulation_test', title: '🎭 Simulación de Envío', icon: '🎭' }
            ];
            
            sections.forEach(section => {
                if (results[section.key]) {
                    html += generateSectionHTML(section.title, results[section.key], section.icon);
                }
            });
            
            return html;
        }

        function generateSectionHTML(title, sectionData, icon) {
            const status = sectionData.status || 'unknown';
            const statusColor = status === 'success' ? 'green' : 
                              status === 'warning' ? 'yellow' : 'red';
            const statusIcon = status === 'success' ? '✅' : 
                             status === 'warning' ? '⚠️' : '❌';
            
            let html = `
                <div class="mb-4">
                    <h5 class="text-md font-medium text-gray-900 mb-2 flex items-center">
                        ${icon} ${title} ${statusIcon}
                    </h5>
                    <div class="bg-${statusColor}-50 border border-${statusColor}-200 rounded-lg p-3">
            `;
            
            // Checks exitosos
            if (sectionData.checks && sectionData.checks.length > 0) {
                html += '<div class="mb-2"><strong class="text-green-700">✅ Verificaciones exitosas:</strong><ul class="ml-4 text-sm text-green-600">';
                sectionData.checks.forEach(check => {
                    html += `<li>• ${check}</li>`;
                });
                html += '</ul></div>';
            }
            
            // Errores
            if (sectionData.errors && sectionData.errors.length > 0) {
                html += '<div class="mb-2"><strong class="text-red-700">❌ Errores:</strong><ul class="ml-4 text-sm text-red-600">';
                sectionData.errors.forEach(error => {
                    html += `<li>• ${error}</li>`;
                });
                html += '</ul></div>';
            }
            
            // Advertencias
            if (sectionData.warnings && sectionData.warnings.length > 0) {
                html += '<div class="mb-2"><strong class="text-yellow-700">⚠️ Advertencias:</strong><ul class="ml-4 text-sm text-yellow-600">';
                sectionData.warnings.forEach(warning => {
                    html += `<li>• ${warning}</li>`;
                });
                html += '</ul></div>';
            }
            
            // Detalles adicionales
            if (sectionData.details && sectionData.details.length > 0) {
                html += '<div class="mb-2"><strong class="text-blue-700">ℹ️ Detalles:</strong><ul class="ml-4 text-sm text-blue-600">';
                sectionData.details.forEach(detail => {
                    html += `<li>• ${detail}</li>`;
                });
                html += '</ul></div>';
            }
            
            // Muestra de XML si está disponible
            if (sectionData.xml_sample) {
                html += `
                    <div class="mt-3">
                        <strong class="text-gray-700">📄 Muestra de XML generado:</strong>
                        <pre class="mt-1 text-xs bg-gray-100 p-2 rounded overflow-x-auto">${sectionData.xml_sample}</pre>
                    </div>
                `;
            }
            
            html += '</div></div>';
            return html;
        }

        // ========================================
        // FUNCIONES DE UTILIDAD
        // ========================================

        function showAlert(type, message) {
            // Crear alerta temporal en la parte superior de la página
            const alertDiv = document.createElement('div');
            const alertClasses = {
                'success': 'bg-green-100 border-green-400 text-green-700',
                'error': 'bg-red-100 border-red-400 text-red-700',
                'warning': 'bg-yellow-100 border-yellow-400 text-yellow-700',
                'info': 'bg-blue-100 border-blue-400 text-blue-700'
            };
            
            alertDiv.className = `fixed top-4 right-4 z-50 ${alertClasses[type]} px-4 py-3 rounded border shadow-lg max-w-md`;
            alertDiv.innerHTML = `
                <div class="flex justify-between items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-lg font-bold">×</button>
                </div>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Cerrar modales al hacer clic fuera de ellos
        document.addEventListener('click', function(event) {
            const modals = ['test-modal', 'bulk-test-modal', 'results-modal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });

        // Cerrar modales con Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = ['test-modal', 'bulk-test-modal', 'results-modal'];
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (!modal.classList.contains('hidden')) {
                        modal.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</x-app-layout>