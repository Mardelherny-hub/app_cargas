<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📋 Listado de Conocimientos de Embarque
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Genere reportes PDF o Excel de conocimientos filtrados por fecha, cliente o puerto
                </p>
            </div>
            <a href="{{ route('company.reports.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                ← Volver a Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- MENSAJES --}}
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('info'))
                <div class="mb-6 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                    {{ session('info') }}
                </div>
            @endif

            {{-- INSTRUCCIONES --}}
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>¿Cómo generar un listado?</strong><br>
                            1. Aplique filtros opcionales (fechas, clientes, puertos, estado)<br>
                            2. Elija el formato deseado (PDF o Excel)<br>
                            3. Haga clic en "Generar Reporte"
                        </p>
                    </div>
                </div>
            </div>

            {{-- FORMULARIO --}}
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Generar Listado de Conocimientos
                    </h3>

                    <form method="POST" action="{{ route('company.reports.export', 'bills-of-lading') }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            
                            {{-- RANGO DE FECHAS --}}
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700">
                                    Desde (Fecha BL)
                                </label>
                                <input type="date" 
                                       id="date_from" 
                                       name="filters[date_from]"
                                       value="{{ old('filters.date_from') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700">
                                    Hasta (Fecha BL)
                                </label>
                                <input type="date" 
                                       id="date_to" 
                                       name="filters[date_to]"
                                       value="{{ old('filters.date_to') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>

                            {{-- ESTADO --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">
                                    Estado
                                </label>
                                <select id="status" 
                                        name="filters[status]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los estados</option>
                                    <option value="draft">Borrador</option>
                                    <option value="verified">Verificado</option>
                                    <option value="sent_to_customs">Enviado a Aduana</option>
                                    <option value="customs_approved">Aprobado por Aduana</option>
                                    <option value="in_transit">En Tránsito</option>
                                    <option value="delivered">Entregado</option>
                                </select>
                            </div>

                            {{-- CARGADOR --}}
                            <div>
                                <label for="shipper_id" class="block text-sm font-medium text-gray-700">
                                    Cargador (opcional)
                                </label>
                                <select id="shipper_id" 
                                        name="filters[shipper_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los cargadores</option>
                                    @php 
                                        $shippers = \App\Models\Client::where('status', 'active')->get();
                                        /* $shippers = $shippers->filter(function($shipper) {
                                            return $shipper->billsOfLadingAsShipper()->whereHas('shipment.voyage', function($q) {
                                                $companyId = auth()->user()->userable->company_id ?? auth()->user()->userable_id;
                                                $q->where('company_id', $companyId);
                                            })->exists();
                                        })->sortBy('legal_name'); */
                                    @endphp
                                    @foreach($shippers as $shipper)
                                        <option value="{{ $shipper->id }}">
                                            {{ $shipper->commercial_name ?: $shipper->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- CONSIGNATARIO --}}
                            <div>
                                <label for="consignee_id" class="block text-sm font-medium text-gray-700">
                                    Consignatario (opcional)
                                </label>
                                <select id="consignee_id" 
                                        name="filters[consignee_id]"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Todos los consignatarios</option>
                                    @php
                                        $consignees = \App\Models\Client::where('status', 'active')
                                        ->orderBy('legal_name')
                                        ->get();
                                    @endphp
                                    @foreach($consignees as $consignee)
                                        <option value="{{ $consignee->id }}">
                                            {{ $consignee->commercial_name ?: $consignee->legal_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- FORMATO --}}
                            <div>
                                <label for="format" class="block text-sm font-medium text-gray-700">
                                    Formato <span class="text-red-500">*</span>
                                </label>
                                <select id="format" 
                                        name="format" 
                                        required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="pdf">PDF (Recomendado)</option>
                                    <option value="excel">Excel</option>
                                </select>
                            </div>
                        </div>

                        {{-- BOTONES --}}
                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="{{ route('company.reports.index') }}" 
                               class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </a>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Generar Reporte
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ESTADÍSTICAS --}}
            @php
                $companyId = auth()->user()->userable->company_id ?? auth()->user()->userable_id;
                $stats = [
                    'total' => \App\Models\BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->count(),
                    'draft' => \App\Models\BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->where('status', 'draft')->count(),
                    'verified' => \App\Models\BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->where('status', 'verified')->count(),
                    'sent' => \App\Models\BillOfLading::whereHas('shipment.voyage', function($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->where('status', 'sent_to_customs')->count(),
                ];
            @endphp

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Estadísticas de Conocimientos
                    </h3>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
                            <div class="text-2xl font-bold text-blue-600">{{ $stats['total'] }}</div>
                            <div class="text-sm text-gray-600">Total</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 border-l-4 border-yellow-500">
                            <div class="text-2xl font-bold text-yellow-600">{{ $stats['draft'] }}</div>
                            <div class="text-sm text-gray-600">Borradores</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
                            <div class="text-2xl font-bold text-green-600">{{ $stats['verified'] }}</div>
                            <div class="text-sm text-gray-600">Verificados</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-500">
                            <div class="text-2xl font-bold text-purple-600">{{ $stats['sent'] }}</div>
                            <div class="text-sm text-gray-600">Enviados</div>
                        </div>
                    </div>

                    @if($stats['total'] === 0)
                        <div class="mt-6 text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay conocimientos</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Comience creando conocimientos de embarque en sus viajes.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>