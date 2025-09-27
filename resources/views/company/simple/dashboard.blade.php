{{-- 
  SISTEMA MODULAR WEBSERVICES - Dashboard Principal
  Ubicación: resources/views/company/simple/dashboard.blade.php
  
  Dashboard unificado con selector de webservices modulares.
  FASE 1: MIC/DTA Argentina activo
  FASE 2-5: Otros webservices preparados
  
  DATOS VERIFICADOS:
  - Variables del controlador: $voyages, $company, $webservice_types, $active_webservices
  - Campos Voyage: voyage_number, leadVessel->name, originPort->code, destinationPort->code
  - Relación webservice_stats agregada en controller
--}}

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Webservices Aduaneros
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Sistema modular para envío de manifiestos y documentación aduanera
                </p>
            </div>
            <div class="flex items-center space-x-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ $company->legal_name }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Mensajes Flash --}}
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Selector de Webservices --}}
            <div class="mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 16a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                            </svg>
                            Tipos de Webservices Disponibles
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($webservice_types as $type => $config)
                                <div class="border rounded-lg p-4 {{ $config['status'] === 'active' ? 'border-green-300 bg-green-50' : 'border-gray-300 bg-gray-50' }}">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <svg class="w-6 h-6 mr-2 {{ $config['status'] === 'active' ? 'text-green-600' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20">
                                                @if($config['icon'] === 'truck')
                                                    <path d="M8 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM15 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                                                    <path d="M3 4a1 1 0 00-1 1v10a1 1 0 001 1h1.05a2.5 2.5 0 014.9 0H10a1 1 0 001-1V5a1 1 0 00-1-1H3zM14 7a1 1 0 00-1 1v6.05A2.5 2.5 0 0115.95 16H17a1 1 0 001-1v-5a1 1 0 00-.293-.707l-2-2A1 1 0 0015 7h-1z"/>
                                                @elseif($config['icon'] === 'clock')
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                @elseif($config['icon'] === 'ship')
                                                    <path d="M3 6l3 1.5L9 6l3 1.5L15 6l3 1.5V16a2 2 0 01-2 2H4a2 2 0 01-2-2V7.5L3 6z"/>
                                                @elseif($config['icon'] === 'boxes')
                                                    <path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
                                                @elseif($config['icon'] === 'exchange-alt')
                                                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z"/>
                                                @else
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                @endif
                                            </svg>
                                            <h4 class="font-medium text-gray-900">{{ $config['name'] }}</h4>
                                        </div>
                                        @if($config['status'] === 'active')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                Próximamente
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <p class="text-sm text-gray-600 mb-3">{{ $config['description'] }}</p>
                                    
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-gray-500">
                                            País: <strong>{{ $config['country'] }}</strong>
                                        </span>
                                        @if($config['status'] === 'active')
                                            <a href="{{ route('company.simple.' . $type . '.index') }}" 
                                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200">
                                                Gestionar
                                                <svg class="ml-1 w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </a>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded text-gray-500 bg-gray-100">
                                                En desarrollo
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lista de Voyages Disponibles --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            <svg class="inline w-5 h-5 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 16a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                            </svg>
                            Voyages Disponibles ({{ $voyages->total() }})
                        </h3>
                        
                        <div class="flex items-center space-x-2">
                            <a href="{{ route('company.voyages.index') }}" 
                               class="text-sm text-indigo-600 hover:text-indigo-900">
                                Ver todos los voyages
                            </a>
                        </div>
                    </div>

                    @if($voyages->count() > 0)
                        <div class="space-y-4">
                            @foreach($voyages as $voyage)
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-4">
                                                <div>
                                                    <h4 class="text-lg font-medium text-gray-900">
                                                        {{ $voyage->voyage_number }}
                                                    </h4>
                                                    <p class="text-sm text-gray-600">
                                                        @if($voyage->leadVessel)
                                                            <span class="font-medium">{{ $voyage->leadVessel->name }}</span>
                                                        @else
                                                            <span class="italic text-gray-400">Sin embarcación asignada</span>
                                                        @endif
                                                    </p>
                                                </div>
                                                
                                                <div class="hidden md:block">
                                                    <p class="text-sm text-gray-600">
                                                        <span class="font-medium">Ruta:</span>
                                                        {{ $voyage->originPort->code ?? 'N/D' }} 
                                                        <svg class="inline w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                        {{ $voyage->destinationPort->code ?? 'N/D' }}
                                                    </p>
                                                </div>
                                                
                                                <div class="hidden lg:block">
                                                    <p class="text-sm text-gray-600">
                                                        <span class="font-medium">Shipments:</span> {{ $voyage->shipments->count() }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        {{-- Estados de Webservices --}}
                                        <div class="flex items-center space-x-3">
                                            @foreach($active_webservices as $type => $config)
                                                @php
                                                    $webservice_status = $voyage->webservice_stats[$type] ?? null;
                                                    $status_color = 'gray';
                                                    $status_text = 'No configurado';
                                                    
                                                    if ($webservice_status) {
                                                        switch($webservice_status['status']) {
                                                            case 'approved':
                                                                $status_color = 'green';
                                                                $status_text = 'Aprobado';
                                                                break;
                                                            case 'sent':
                                                                $status_color = 'blue';
                                                                $status_text = 'Enviado';
                                                                break;
                                                            case 'pending':
                                                                $status_color = 'yellow';
                                                                $status_text = 'Pendiente';
                                                                break;
                                                            case 'error':
                                                                $status_color = 'red';
                                                                $status_text = 'Error';
                                                                break;
                                                            default:
                                                                $status_color = 'gray';
                                                                $status_text = 'Sin estado';
                                                        }
                                                    }
                                                @endphp
                                                
                                                <div class="text-center">
                                                    <div class="text-xs font-medium text-gray-700 mb-1">{{ $config['name'] }}</div>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                                        @if($status_color === 'green') bg-green-100 text-green-800
                                                        @elseif($status_color === 'blue') bg-blue-100 text-blue-800
                                                        @elseif($status_color === 'yellow') bg-yellow-100 text-yellow-800
                                                        @elseif($status_color === 'red') bg-red-100 text-red-800
                                                        @else bg-gray-100 text-gray-800 @endif">
                                                        {{ $status_text }}
                                                    </span>
                                                    @if($webservice_status && $webservice_status['can_send'])
                                                        <div class="mt-1">
                                                            <a href="{{ route('company.simple.' . $type . '.show', $voyage->id) }}" 
                                                               class="text-xs text-indigo-600 hover:text-indigo-900">
                                                                Gestionar
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Paginación --}}
                        @if($voyages->hasPages())
                            <div class="mt-6">
                                {{ $voyages->links() }}
                            </div>
                        @endif
                        
                    @else
                        {{-- Estado vacío --}}
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No hay voyages disponibles</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Crea un nuevo Viaje para comenzar a usar los webservices aduaneros.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('company.voyages.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Crear Voyage
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>