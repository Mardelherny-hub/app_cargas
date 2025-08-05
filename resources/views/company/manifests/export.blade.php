<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📤 Exportar Manifiestos
            </h2>
            <a href="{{ route('company.manifests.index') }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                ← Volver a Manifiestos
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Descripción del proceso -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Exportación de Manifiestos
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>
                                Seleccione un viaje para exportar su manifiesto en diferentes formatos:
                                <strong>PARANA.xlsx</strong>, <strong>Guaran.csv</strong>, <strong>Login.xml</strong>, <strong>TFP.txt</strong>, o <strong>EDI/CUSCAR</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Listado de viajes para exportar -->
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Seleccionar Viaje para Exportar
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">
                        Solo se muestran viajes con cargas completadas
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Viaje
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ruta
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Cargas
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Estado
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Exportar Como
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($voyages as $voyage)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $voyage->voyage_number }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $voyage->created_at->format('d/m/Y') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $voyage->origin_port->name ?? 'N/A' }} → {{ $voyage->destination_port->name ?? 'N/A' }}
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $voyage->origin_port->country->name ?? '' }} - {{ $voyage->destination_port->country->name ?? '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ $voyage->shipments->count() }} cargas
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $voyage->shipments->sum(function($s) { return $s->billsOfLading->count(); }) }} conocimientos
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        @if($voyage->status === 'completed') bg-green-100 text-green-800
                                        @elseif($voyage->status === 'in_progress') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($voyage->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex flex-wrap gap-2">
                                        <!-- PARANA Excel -->
                                        <a href="{{ route('company.manifests.export.parana', $voyage->id) }}" 
                                           class="bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200"
                                           title="Exportar como PARANA.xlsx">
                                            📊 PARANA
                                        </a>
                                        
                                        <!-- Guaran CSV -->
                                        <a href="{{ route('company.manifests.export.guaran', $voyage->id) }}" 
                                           class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200"
                                           title="Exportar como Guaran.csv">
                                            📄 Guaran
                                        </a>
                                        
                                        <!-- Login XML -->
                                        <a href="{{ route('company.manifests.export.login', $voyage->id) }}" 
                                           class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200"
                                           title="Exportar como Login.xml">
                                            🔗 XML
                                        </a>
                                        
                                        <!-- TFP Text -->
                                        <a href="{{ route('company.manifests.export.tfp', $voyage->id) }}" 
                                           class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200"
                                           title="Exportar como TFP.txt">
                                            📝 TFP
                                        </a>
                                        
                                        <!-- EDI -->
                                        <a href="{{ route('company.manifests.export.edi', $voyage->id) }}" 
                                           class="bg-red-100 hover:bg-red-200 text-red-800 px-3 py-1 rounded-md text-xs font-medium transition-colors duration-200"
                                           title="Exportar como EDI/CUSCAR">
                                            📡 EDI
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                    No hay viajes disponibles para exportar.
                                    <a href="{{ route('company.manifests.create') }}" class="text-blue-600 hover:text-blue-500 ml-2">
                                        Crear nuevo manifiesto →
                                    </a>
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

            <!-- Información sobre formatos -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- PARANA -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">📊 PARANA.xlsx</h4>
                    <p class="text-sm text-gray-600">Formato Excel estándar MAERSK con 73 columnas. Ideal para líneas navieras grandes.</p>
                </div>
                
                <!-- Guaran -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">📄 Guaran.csv</h4>
                    <p class="text-sm text-gray-600">Formato CSV para manifiestos consolidados multi-línea y multi-destino.</p>
                </div>
                
                <!-- Login XML -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">🔗 Login.xml</h4>
                    <p class="text-sm text-gray-600">Estructura XML anidada completa por conocimiento. Ideal para sistemas integrados.</p>
                </div>
                
                <!-- TFP -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">📝 TFP.txt</h4>
                    <p class="text-sm text-gray-600">Formato jerárquico con delimitadores específicos. Para sistemas legacy.</p>
                </div>
                
                <!-- EDI -->
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">📡 EDI/CUSCAR</h4>
                    <p class="text-sm text-gray-600">Estándar UN/EDIFACT para intercambio electrónico de datos aduaneros.</p>
                </div>
            </div>

            <!-- Estadísticas de exportación -->
            <div class="mt-6 bg-white overflow-hidden shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        📈 Resumen de Exportaciones
                    </h3>
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Viajes Disponibles</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ $voyages->total() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Cargas</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ $voyages->sum(function($v) { return $v->shipments->count(); }) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Total Conocimientos</dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ $voyages->sum(function($v) { return $v->shipments->sum(function($s) { return $s->billsOfLading->count(); }); }) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Ayuda y documentación -->
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">
                            💡 Consejos para Exportación
                        </h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>PARANA.xlsx</strong>: Mejor para MAERSK y líneas navieras grandes</li>
                                <li><strong>Guaran.csv</strong>: Ideal para manifiestos consolidados multi-destino</li>
                                <li><strong>Login.xml</strong>: Para integración con sistemas automáticos</li>
                                <li><strong>TFP.txt</strong>: Compatible con sistemas legacy y equipos antiguos</li>
                                <li><strong>EDI/CUSCAR</strong>: Estándar internacional para aduanas</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>