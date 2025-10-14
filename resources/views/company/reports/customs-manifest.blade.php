{{-- 
  Vista de Formulario - Manifiesto Aduanero
  Ubicación: resources/views/company/reports/customs-manifest.blade.php
  
  Reporte oficial para presentación ante autoridades aduaneras
  con códigos aduaneros, datos de transbordo y formato oficial.
--}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    📋 Manifiesto Aduanero
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Reporte oficial para presentación ante autoridades aduaneras
                </p>
            </div>
            <a href="{{ route('company.reports.index') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Volver a Reportes
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Mensajes de éxito/error --}}
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Información del reporte --}}
            <div class="mb-6 bg-gradient-to-r from-red-50 to-orange-50 border-l-4 border-red-500 p-4 rounded-r-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-medium text-red-800">
                            Sobre este reporte
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li><strong>Formato oficial</strong> para presentación física ante autoridades aduaneras</li>
                                <li>Incluye <strong>códigos aduaneros</strong>, posiciones arancelarias y datos de transbordo</li>
                                <li>Documento <strong>imprimible en formato PDF apaisado</strong> (landscape)</li>
                                <li>Diferente del MIC/DTA (que es formato XML para webservices AFIP)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Formulario de filtros --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        🔍 Filtros de Generación
                    </h3>

                    <form method="POST" action="{{ route('company.reports.export', 'customs-manifest') }}" class="space-y-6">
    @csrf

    {{-- Selección de Viaje (OBLIGATORIO) --}}
    <div>
        <label for="voyage_id" class="block text-sm font-medium text-gray-700">
            Viaje <span class="text-red-600">*</span>
        </label>
        <select name="voyage_id" id="voyage_id" required
                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
            <option value="">Seleccione un viaje...</option>
            @foreach($voyages as $voyage)
                <option value="{{ $voyage->id }}">
                    {{ $voyage->voyage_number }} - 
                    {{ $voyage->leadVessel->name ?? 'Sin embarcación' }} - 
                    {{ $voyage->originPort->code ?? '' }} → {{ $voyage->destinationPort->code ?? '' }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Filtro opcional: Puerto de Descarga --}}
    <div>
        <label for="discharge_port_id" class="block text-sm font-medium text-gray-700">
            Puerto de Descarga (Opcional)
        </label>
        <select name="discharge_port_id" id="discharge_port_id"
                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
            <option value="">Todos los puertos</option>
            @foreach($ports as $port)
                <option value="{{ $port->id }}">
                    {{ $port->name }} ({{ $port->code }})
                </option>
            @endforeach
        </select>
    </div>

    {{-- Filtro opcional: Consignatario --}}
    <div>
        <label for="consignee_id" class="block text-sm font-medium text-gray-700">
            Consignatario (Opcional)
        </label>
        <select name="consignee_id" id="consignee_id"
                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm rounded-md">
            <option value="">Todos los consignatarios</option>
            @foreach($consignees as $consignee)
                <option value="{{ $consignee->id }}">
                    {{ $consignee->legal_name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Formato --}}
    <input type="hidden" name="format" value="pdf">

    {{-- BOTÓN DE ENVÍO --}}
    <div class="pt-4 border-t border-gray-200">
        <button type="submit"
                class="w-full inline-flex justify-center items-center px-6 py-3 bg-gradient-to-r from-red-600 to-red-700 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Generar Manifiesto Aduanero (PDF)
        </button>
    </div>
</form>
                </div>
            </div>

            {{-- Estadísticas --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        📊 Estadísticas Generales
                    </h3>
                    
                    <dl class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                        <div class="bg-blue-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-blue-600">Viajes Disponibles</dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-900">{{ $stats['total_voyages'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-green-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-green-600">Conocimientos Totales</dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-900">{{ $stats['total_bills'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-purple-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-purple-600">Puertos Activos</dt>
                            <dd class="mt-1 text-2xl font-semibold text-purple-900">{{ $stats['total_ports'] ?? 0 }}</dd>
                        </div>
                        <div class="bg-orange-50 px-4 py-4 rounded-lg">
                            <dt class="text-sm font-medium text-orange-600">Consignatarios</dt>
                            <dd class="mt-1 text-2xl font-semibold text-orange-900">{{ $stats['total_consignees'] ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>