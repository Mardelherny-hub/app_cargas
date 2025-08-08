<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conocimiento de Embarque') }} - {{ $billOfLading->bill_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Header con acciones --}}
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">
                                Conocimiento de Embarque
                            </h1>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ $billOfLading->bill_number }} - {{ $billOfLading->status_label }}
                            </p>
                        </div>
                        
                        {{-- REEMPLAZAR en tu vista show.blade.php --}}

                        <div class="flex space-x-3">
                            {{-- Botón Volver --}}
                            <a href="{{ route('company.bills-of-lading.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Volver
                            </a>

                            {{-- Botón Editar - CLAVE CORREGIDA --}}
                            @if(isset($permissions['canEdit']) && $permissions['canEdit'])
                                <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}"
                                class="inline-flex items-center px-4 py-2 border border-blue-300 rounded-md shadow-sm text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                    Editar
                                </a>
                            @endif

                            {{-- Botón Verificar --}}
                            @if(isset($permissions['canVerify']) && $permissions['canVerify'])
                                <form method="POST" action="{{ route('company.bills-of-lading.verify', $billOfLading) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            onclick="return confirm('¿Está seguro de verificar este conocimiento? Esta acción no se puede deshacer.')"
                                            class="inline-flex items-center px-4 py-2 border border-green-300 rounded-md shadow-sm text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Verificar
                                    </button>
                                </form>
                            @endif

                            {{-- Botón Duplicar --}}
                            @if(isset($permissions['canDuplicate']) && $permissions['canDuplicate'])
                                <form method="POST" action="{{ route('company.bills-of-lading.duplicate', $billOfLading) }}" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            onclick="return confirm('¿Crear una copia de este conocimiento?')"
                                            class="inline-flex items-center px-4 py-2 border border-purple-300 rounded-md shadow-sm text-sm font-medium text-purple-700 bg-purple-50 hover:bg-purple-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                        Duplicar
                                    </button>
                                </form>
                            @endif

                            {{-- Botón PDF (siempre visible) --}}
                            @if(isset($permissions['canGeneratePdf']) && $permissions['canGeneratePdf'])
                                <a href="{{ route('company.bills-of-lading.pdf', $billOfLading) }}" 
                                target="_blank"
                                class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    PDF
                                </a>
                            @endif

                            {{-- Botón Eliminar (solo estado draft) --}}
                            @if(isset($permissions['canDelete']) && $permissions['canDelete'])
                                <form method="POST" action="{{ route('company.bills-of-lading.destroy', $billOfLading) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            onclick="return confirm('¿Está seguro de eliminar este conocimiento? Esta acción no se puede deshacer.')"
                                            class="inline-flex items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Eliminar
                                    </button>
                                </form>
                            @endif
                        </div>

                        
                    </div>
                </div>
            </div>

            {{-- Información Principal del Conocimiento --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
                
                {{-- Datos Básicos --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Información Básica
                        </h3>
                        
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Número de Conocimiento</dt>
                                <dd class="text-sm text-gray-900 font-mono">{{ $billOfLading->bill_number }}</dd>
                            </div>
                            
                            @if($billOfLading->internal_reference)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Referencia Interna</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->internal_reference }}</dd>
                            </div>
                            @endif
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha del Conocimiento</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->bill_date->format('d/m/Y') }}</dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Estado</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($billOfLading->status)
                                            @case('draft') bg-gray-100 text-gray-800 @break
                                            @case('verified') bg-green-100 text-green-800 @break
                                            @case('pending_review') bg-yellow-100 text-yellow-800 @break
                                            @case('accepted') bg-blue-100 text-blue-800 @break
                                            @case('rejected') bg-red-100 text-red-800 @break
                                            @default bg-gray-100 text-gray-800
                                        @endswitch">
                                        {{ $billOfLading->status_label }}
                                    </span>
                                </dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Prioridad</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @switch($billOfLading->priority_level)
                                            @case('low') bg-gray-100 text-gray-800 @break
                                            @case('normal') bg-green-100 text-green-800 @break
                                            @case('high') bg-yellow-100 text-yellow-800 @break
                                            @case('urgent') bg-red-100 text-red-800 @break
                                        @endswitch">
                                        {{ $billOfLading->priority_label }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Envío Asociado --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Envío
                        </h3>
                        
                        <dl class="space-y-3">
                            @if($billOfLading->shipment)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Número de Envío</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipment->shipment_number ?? 'N/A' }}</dd>
                            </div>
                            @endif
                            
                            @if($billOfLading->shipment?->voyage)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Viaje</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipment->voyage->voyage_number }}</dd>
                            </div>
                            @endif
                            
                            @if($billOfLading->shipment?->vessel)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Embarcación</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipment->vessel->name }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->manifest_number)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Manifiesto</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->manifest_number }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->manifest_line_number)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Línea de Manifiesto</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->manifest_line_number }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Fechas --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Fechas Operacionales
                        </h3>
                        
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Carga</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->loading_date->format('d/m/Y H:i') }}</dd>
                            </div>
                            
                            @if($billOfLading->discharge_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Descarga</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->discharge_date->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif
                            
                            @if($billOfLading->arrival_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Arribo</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->arrival_date->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->delivery_date)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Fecha de Entrega</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->delivery_date->format('d/m/Y H:i') }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->free_time_expires_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Vence Tiempo Libre</dt>
                                <dd class="text-sm text-gray-900 @if($billOfLading->is_expired) text-red-600 font-medium @endif">
                                    {{ $billOfLading->free_time_expires_at->format('d/m/Y H:i') }}
                                    @if($billOfLading->is_expired)
                                        <span class="text-red-600 text-xs">(Vencido)</span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Partes Involucradas --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                
                {{-- Cargador --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Cargador/Exportador
                        </h3>
                        
                        @if($billOfLading->shipper)
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipper->legal_name }}</dd>
                            </div>
                            
                            @if($billOfLading->shipper->commercial_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipper->commercial_name }}</dd>
                            </div>
                            @endif
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                <dd class="text-sm text-gray-900 font-mono">{{ $billOfLading->shipper->tax_id }}</dd>
                            </div>
                            
                            @if($billOfLading->shipper->address)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->shipper->address }}</dd>
                            </div>
                            @endif
                        </dl>
                        @else
                        <p class="text-sm text-gray-500 italic">No especificado</p>
                        @endif
                    </div>
                </div>

                {{-- Consignatario --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Consignatario/Importador
                        </h3>
                        
                        @if($billOfLading->consignee)
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Razón Social</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->consignee->legal_name }}</dd>
                            </div>
                            
                            @if($billOfLading->consignee->commercial_name)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nombre Comercial</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->consignee->commercial_name }}</dd>
                            </div>
                            @endif
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">CUIT/RUC</dt>
                                <dd class="text-sm text-gray-900 font-mono">{{ $billOfLading->consignee->tax_id }}</dd>
                            </div>
                            
                            @if($billOfLading->consignee->address)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dirección</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->consignee->address }}</dd>
                            </div>
                            @endif
                        </dl>
                        @else
                        <p class="text-sm text-gray-500 italic">No especificado</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Carga y Medidas --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">
                
                {{-- Descripción de Carga --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            Descripción de Carga
                        </h3>
                        
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Descripción</dt>
                                <dd class="text-sm text-gray-900 whitespace-pre-line">{{ $billOfLading->cargo_description }}</dd>
                            </div>

                            @if($billOfLading->cargo_marks)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Marcas</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->cargo_marks }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->commodity_code)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Código NCM</dt>
                                <dd class="text-sm text-gray-900 font-mono">{{ $billOfLading->commodity_code }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->primaryCargoType)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Carga</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->primaryCargoType->name }}</dd>
                            </div>
                            @endif

                            @if($billOfLading->primaryPackagingType)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Tipo de Embalaje</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->primaryPackagingType->name }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Medidas y Pesos --}}
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                            </svg>
                            Medidas y Pesos
                        </h3>
                        
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Bultos</dt>
                                <dd class="text-sm text-gray-900 font-semibold">{{ number_format($billOfLading->total_packages) }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Peso Bruto</dt>
                                <dd class="text-sm text-gray-900 font-semibold">{{ number_format($billOfLading->gross_weight_kg, 2) }} kg</dd>
                            </div>

                            @if($billOfLading->net_weight_kg)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Peso Neto</dt>
                                <dd class="text-sm text-gray-900">{{ number_format($billOfLading->net_weight_kg, 2) }} kg</dd>
                            </div>
                            @endif

                            @if($billOfLading->volume_m3)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Volumen</dt>
                                <dd class="text-sm text-gray-900">{{ number_format($billOfLading->volume_m3, 3) }} m³</dd>
                            </div>
                            @endif

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Contenedores</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->container_count }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Información de Auditoría --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Información de Auditoría
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Creado</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            
                            @if($billOfLading->createdByUser)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Creado por</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->createdByUser->name }}</dd>
                            </div>
                            @endif
                        </dl>

                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Actualizado</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->updated_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            
                            @if($billOfLading->lastUpdatedByUser)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Actualizado por</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->lastUpdatedByUser->name }}</dd>
                            </div>
                            @endif
                        </dl>

                        @if($billOfLading->verified_at)
                        <dl class="space-y-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Verificado</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->verified_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            
                            @if($billOfLading->verifiedByUser)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Verificado por</dt>
                                <dd class="text-sm text-gray-900">{{ $billOfLading->verifiedByUser->name }}</dd>
                            </div>
                            @endif
                        </dl>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>