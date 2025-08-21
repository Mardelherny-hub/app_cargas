<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Conocimiento de Embarque') }} - {{ $billOfLading->bill_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- LAYOUT REDISEÑADO PARA SHOW.BLADE.PHP --}}
{{-- Reemplazar desde línea ~184 (después del header con acciones) --}}

{{-- Layout Principal en 2 Columnas cuando BL está vacío --}}
@if($billOfLading->status === 'draft' && $billOfLading->shipmentItems->count() === 0)
    
    {{-- Layout de 2 Columnas para BL Vacío --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        
        {{-- COLUMNA IZQUIERDA - Wizard y Estado de Items (2/3 del ancho) --}}
        <div class="xl:col-span-2 space-y-6">
            
            {{-- Wizard Principal - Ubicación Prominente --}}
            <div class="bg-gradient-to-br from-blue-50 to-indigo-100 rounded-lg border border-blue-200 shadow-lg" id="itemsWizard">
                {{-- Header del Wizard --}}
                <div class="px-6 py-4 border-b border-blue-200 bg-white bg-opacity-70 rounded-t-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-blue-900 flex items-center">
                                <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v4a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2zM9 5a2 2 0 012 2v2a2 2 0 01-2 2M9 5a2 2 0 012 2v2a2 2 0 01-2 2m0 0h2a2 2 0 012 2v2a2 2 0 01-2 2H9a2 2 0 01-2-2v-2a2 2 0 012-2zm0 0v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 012-2h2a2 2 0 012 2z"/>
                                </svg>
                                Agregar Items de Mercadería
                            </h2>
                            <p class="text-sm text-blue-700 mt-1">
                                Su conocimiento <strong>{{ $billOfLading->bill_number }}</strong> está listo para recibir items
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                Pendiente Items
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Progreso Visual --}}
                <div class="px-6 py-3 bg-white bg-opacity-50">
                    <div class="flex items-center justify-center">
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <span class="ml-2 text-green-700 font-medium text-sm">1. BL Creado</span>
                            </div>
                            <div class="h-1 w-12 bg-blue-300 rounded"></div>
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center animate-pulse">
                                    <span class="text-sm font-bold text-white">2</span>
                                </div>
                                <span class="ml-2 text-blue-700 font-medium text-sm">Agregar Items</span>
                            </div>
                            <div class="h-1 w-12 bg-gray-300 rounded"></div>
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-bold text-gray-500">3</span>
                                </div>
                                <span class="ml-2 text-gray-500 text-sm">Verificar</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Opciones de Carga - Mejoradas --}}
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        
                        {{-- Opción 1: Importación Masiva --}}
                        <div class="relative">
                            <a href="{{ route('company.bills-of-lading.template', $billOfLading) }}" 
                            class="method-card block p-5 border-2 border-purple-400 bg-purple-50 rounded-xl hover:border-purple-500 transition-all shadow-md hover:shadow-lg">
                                <div class="text-center">
                                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-purple-900 mb-2">📋 Plantilla Excel</h4>
                                    <p class="text-xs text-purple-700 mb-3">Descargar y completar</p>
                                    <div class="text-xs text-purple-600 leading-relaxed">
                                        💾 Trabajo offline<br>
                                        🔄 Reutilizable<br>
                                        📏 Con ejemplos incluidos
                                    </div>
                                    <div class="mt-3">
                                        <span class="px-3 py-1 bg-purple-200 text-purple-800 text-xs rounded-full font-semibold">DISPONIBLE</span>
                                    </div>
                                </div>
                            </a>
                        </div>

                        {{-- Opción 2: Item Individual --}}
                        <div class="relative">
                            <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" 
                            class="method-card block p-5 border-2 border-gray-200 bg-white rounded-xl hover:border-blue-400 transition-all shadow-md hover:shadow-lg">
                                <div class="text-center">
                                    <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-gray-900 mb-2">✏️ Item Individual</h4>
                                    <p class="text-xs text-gray-600 mb-3">Un item por vez</p>
                                    <div class="text-xs text-gray-500 leading-relaxed">
                                        🎯 Control total<br>
                                        📝 Formulario completo<br>
                                        👥 Ideal para pocos items
                                    </div>
                                    <div class="mt-3">
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full font-semibold">DISPONIBLE</span>
                                    </div>
                                </div>
                            </a>
                        </div>

                        {{-- Opción 3: Plantilla --}}
                        <div class="relative">
                            <div class="method-card block p-5 border-2 border-green-400 bg-green-50 rounded-xl shadow-md hover:shadow-lg">
                                <div class="text-center">
                                    <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                        </svg>
                                    </div>
                                    <h4 class="font-semibold text-green-900 mb-2">📂 Importación Masiva</h4>
                                    <p class="text-xs text-green-700 mb-3">Subir Excel completado</p>
                                    <div class="text-xs text-green-600 leading-relaxed">
                                        ⚡ Múltiples items<br>
                                        📊 Excel/CSV<br>
                                        🔍 Procesamiento automático
                                    </div>
                                    <div class="mt-3">
                                        <span class="px-3 py-1 bg-green-200 text-green-800 text-xs rounded-full font-semibold">DISPONIBLE</span>
                                    </div>
                                </div>
                                
                                <!-- Form de upload dentro de la tarjeta -->
                                <form action="{{ route('company.bills-of-lading.import-items', $billOfLading) }}" 
                                    method="POST" enctype="multipart/form-data" class="mt-4">
                                    @csrf
                                    <div class="border-2 border-dashed border-green-300 rounded-lg p-4 bg-white">
                                        <input type="file" name="import_file" id="import_file" 
                                            accept=".xlsx,.xls,.csv" required class="sr-only"
                                            onchange="updateFileDisplay(this)">
                                        <label for="import_file" class="cursor-pointer block text-center">
                                            <svg class="w-8 h-8 text-green-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                            </svg>
                                            <p class="text-sm text-green-600 font-medium" id="file-display">
                                                Seleccionar archivo Excel
                                            </p>
                                            <p class="text-xs text-green-500">Máx. 10MB</p>
                                        </label>
                                    </div>
                                    <button type="submit" 
                                            class="w-full mt-3 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-medium">
                                        📊 Procesar e Importar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Contenido Dinámico --}}
                    <div id="method-content" class="mb-6">
                        
                        {{-- Contenido para Importación Masiva --}}
                        <div id="content-bulk" class="method-content">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-5">
                                <h5 class="font-semibold text-green-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                    </svg>
                                    Importar Múltiples Items
                                </h5>
                                
                                {{-- Área de Drop/Upload Mejorada --}}
                                <div class="border-2 border-dashed border-green-300 rounded-lg p-8 mb-4 text-center bg-white hover:border-green-400 transition-colors">
                                    <svg class="w-12 h-12 text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <p class="text-sm text-green-600 mb-2 font-medium">
                                        <span class="underline cursor-pointer">Haga clic para seleccionar</span> o arrastre archivos aquí
                                    </p>
                                    <p class="text-xs text-green-500">Excel (.xlsx, .xls), CSV, XML, EDI • Máximo 10MB</p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-3">
                                    <button type="button" onclick="startBulkImport()" 
                                            class="flex-1 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                                        📊 Subir e Importar
                                    </button>
                                    <button type="button" onclick="showFormatInfo()" 
                                            class="border-2 border-green-600 text-green-600 px-6 py-3 rounded-lg hover:bg-green-50 transition-colors font-medium">
                                        📖 Ver Formato
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Contenido para Item Individual --}}
                        <div id="content-individual" class="method-content hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-5">
                                <h5 class="font-semibold text-blue-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Agregar Item Individual
                                </h5>
                                <p class="text-sm text-blue-700 mb-4">
                                    Use el formulario completo para crear un item con control total sobre cada campo.
                                </p>
                                
                                <div class="bg-white rounded-lg p-4 mb-4 border border-blue-200">
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Próximo número de línea:</span>
                                            <span class="font-semibold text-blue-900">1</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Items existentes:</span>
                                            <span class="font-semibold text-blue-900">0</span>
                                        </div>
                                    </div>
                                </div>

                                <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" 
                                class="block w-full bg-blue-600 text-white text-center px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    ✏️ Agregar Item Individual
                                </a>
                            </div>
                        </div>

                        {{-- Contenido para Plantilla --}}
                        <div id="content-template" class="method-content hidden">
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-5">
                                <h5 class="font-semibold text-purple-900 mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Plantilla Excel Pre-configurada
                                </h5>
                                <p class="text-sm text-purple-700 mb-4">
                                    Descargue una plantilla con datos del BL pre-poblados, complete offline y luego súbala.
                                </p>
                                
                                <div class="bg-white rounded-lg p-4 mb-4 border border-purple-200">
                                    <h6 class="font-semibold text-gray-900 mb-3">La plantilla incluye:</h6>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            Columnas nombradas
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            Datos BL incluidos
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            Ejemplos incluidos
                                        </div>
                                        <div class="flex items-center text-sm text-gray-600">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                            Validaciones activas
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-3">
                                    <button type="button" onclick="downloadTemplate('excel')" 
                                            class="flex-1 bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition-colors font-medium">
                                        📥 Descargar Excel
                                    </button>
                                    <button type="button" onclick="downloadTemplate('csv')" 
                                            class="border-2 border-purple-600 text-purple-600 px-6 py-3 rounded-lg hover:bg-purple-50 transition-colors font-medium">
                                        📄 CSV
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Botones de Acción Principales --}}
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 pt-4 border-t border-blue-200">
                        <button type="button" onclick="hideWizard()" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            Completar más tarde
                        </button>
                        <div class="flex gap-3">
                            <a href="{{ route('company.bills-of-lading.edit', $billOfLading) }}" 
                               class="border-2 border-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-50 transition-colors font-medium">
                                ✏️ Editar BL
                            </a>
                            <button type="button" onclick="proceedWithSelectedMethod()" 
                                    class="bg-blue-600 text-white px-8 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-md">
                                Continuar →
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card de Estado de Items --}}
            <div class="bg-white shadow rounded-lg border-l-4 border-yellow-400">
                <div class="px-6 py-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-6.5a2 2 0 00-1.5.67l-.5.83a2 2 0 01-1.5.83H9a2 2 0 01-1.5-.83l-.5-.83a2 2 0 00-1.5-.67H2"/>
                        </svg>
                        Items de Mercadería
                    </h3>
                    
                    {{-- Estado vacío con llamada a la acción --}}
                    <div class="text-center py-8">
                        <div class="mx-auto h-16 w-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="h-8 w-8 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-6.5a2 2 0 00-1.5.67l-.5.83a2 2 0 01-1.5.83H9a2 2 0 01-1.5-.83l-.5-.83a2 2 0 00-1.5-.67H2"/>
                            </svg>
                        </div>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Sin items de mercadería</h4>
                        <p class="text-sm text-gray-600 mb-4">
                            Este conocimiento necesita items para ser válido. Use el wizard de la izquierda para agregar mercadería.
                        </p>
                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm text-yellow-800 font-medium">
                                    Acción requerida: Agregue al menos un item para continuar
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMNA DERECHA - Información del Conocimiento (1/3 del ancho) --}}
        <div class="xl:col-span-1 space-y-6">
            
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
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Fecha del Conocimiento</dt>
                            <dd class="text-sm text-gray-900">{{ $billOfLading->bill_date->format('d/m/Y') }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    {{ $billOfLading->status_label }}
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
                    </dl>
                </div>
            </div>

            {{-- Partes Involucradas --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Partes
                    </h3>
                    
                    <dl class="space-y-3">
                        @if($billOfLading->shipper)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Cargador</dt>
                            <dd class="text-sm text-gray-900">{{ $billOfLading->shipper->legal_name }}</dd>
                        </div>
                        @endif
                        
                        @if($billOfLading->consignee)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Consignatario</dt>
                            <dd class="text-sm text-gray-900">{{ $billOfLading->consignee->legal_name }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Información Adicional --}}
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h6 class="text-sm font-medium text-blue-900">💡 Recomendaciones</h6>
                        <div class="mt-2 text-sm text-blue-700 space-y-2">
                            <div>
                                <p class="font-medium mb-1">Cuándo usar cada método:</p>
                                <ul class="space-y-1 text-xs">
                                    <li>• <strong>1-5 items:</strong> Individual</li>
                                    <li>• <strong>6+ items:</strong> Masiva</li>
                                    <li>• <strong>Archivos existentes:</strong> Importar</li>
                                    <li>• <strong>Datos nuevos:</strong> Plantilla</li>
                                </ul>
                            </div>
                            <div>
                                <p class="font-medium mb-1">Campos obligatorios:</p>
                                <ul class="space-y-1 text-xs">
                                    <li>• Descripción del item</li>
                                    <li>• Peso bruto y neto (kg)</li>
                                    <li>• Tipo de carga y empaque</li>
                                    <li>• País de origen (ISO 3 letras)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@else
    {{-- Layout Normal cuando el BL ya tiene items --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mb-6">
        {{-- Columna principal con items --}}
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Items de Mercadería ({{ $billOfLading->shipmentItems->count() }})
                        </h3>

                        {{-- Botón para agregar items directamente al conocimiento --}}
                        @if(in_array($billOfLading->status, ['draft', 'pending_review']) && isset($permissions['canEdit']) && $permissions['canEdit'])
                            <a href="{{ route('company.shipment-items.create', ['bill_of_lading_id' => $billOfLading->id]) }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                Agregar Item
                            </a>
                        @endif
                         <a href="{{ route('company.shipments.show', $shipment) }}"
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Terminar
                            </a>
                    </div>

                    {{-- Lista de items existentes --}}
                    @if($billOfLading->shipmentItems && $billOfLading->shipmentItems->count() > 0)
                        <div class="space-y-3">
                            @foreach($billOfLading->shipmentItems as $item)
                                <div class="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3">
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-800 text-sm font-medium">
                                                {{ $item->line_number }}
                                            </span>
                                            <div>
                                                <div class="font-medium text-sm text-gray-900">{{ $item->item_reference }}</div>
                                                <div class="text-sm text-gray-600 mt-1">{{ Str::limit($item->item_description, 60) }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                                            <span>{{ $item->cargoType->name ?? 'N/A' }}</span>
                                            <span>{{ number_format($item->gross_weight_kg, 2) }} kg</span>
                                            <span>{{ $item->package_quantity }} bultos</span>
                                            @if($item->declared_value)
                                                <span>USD {{ number_format($item->declared_value, 2) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="{{ route('company.shipment-items.show', $item) }}" 
                                           class="text-blue-600 hover:text-blue-900 text-sm font-medium">Ver</a>
                                        @if(in_array($billOfLading->status, ['draft', 'pending_review']) && isset($permissions['canEdit']) && $permissions['canEdit'])
                                            <a href="{{ route('company.shipment-items.edit', $item) }}" 
                                               class="text-yellow-600 hover:text-yellow-900 text-sm font-medium">Editar</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Resumen compacto --}}
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $billOfLading->shipmentItems->count() }} items</span>
                                <span class="text-gray-600">{{ number_format($billOfLading->shipmentItems->sum('package_quantity')) }} bultos</span>
                                <span class="text-gray-600">{{ number_format($billOfLading->shipmentItems->sum('gross_weight_kg'), 2) }} kg</span>
                                <span class="text-gray-600">USD {{ number_format($billOfLading->shipmentItems->sum('declared_value'), 2) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-6 text-gray-500">
                            <p>No hay items de mercadería en este conocimiento.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Columna de información del BL --}}
        <div class="lg:col-span-1">
            {{-- Card con información básica (aquí va el contenido de la columna derecha original) --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Información del BL</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Estado</dt>
                            <dd>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    {{ $billOfLading->status_label }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Número</dt>
                            <dd class="text-sm text-gray-900 font-mono">{{ $billOfLading->bill_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Fecha</dt>
                            <dd class="text-sm text-gray-900">{{ $billOfLading->bill_date->format('d/m/Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- JavaScript mejorado para el Wizard --}}
{{-- JavaScript ÚNICO para el Wizard --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar selección de métodos
    const methodCards = document.querySelectorAll('.method-card');
    
    methodCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remover selección anterior
            methodCards.forEach(c => {
                c.classList.remove('border-green-500', 'border-blue-500', 'border-purple-500');
                c.classList.add('border-gray-200');
            });
            
            // Aplicar selección actual
            this.classList.remove('border-gray-200');
            if (this.querySelector('h4').textContent.includes('Excel')) {
                this.classList.add('border-purple-500');
            } else if (this.querySelector('h4').textContent.includes('Individual')) {
                this.classList.add('border-blue-500');
            } else if (this.querySelector('h4').textContent.includes('Masiva')) {
                this.classList.add('border-green-500');
            }
        });
    });

    // Manejar display de archivo seleccionado
    window.updateFileDisplay = function(input) {
        const fileDisplay = document.getElementById('file-display');
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
            
            fileDisplay.innerHTML = `
                <div class="text-green-700 font-medium">${fileName}</div>
                <div class="text-green-600 text-xs">${fileSize} MB</div>
            `;
        }
    };

    // Validación antes de envío
    const importForm = document.querySelector('form[action*="import-items"]');
    if (importForm) {
        importForm.addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                alert('Por favor seleccione un archivo para importar.');
                return;
            }
            
            const file = fileInput.files[0];
            if (!file.name.match(/\.(csv|xlsx|xls)$/i)) {
                e.preventDefault();
                alert('Tipo de archivo no válido. Solo se permiten archivos CSV, XLS y XLSX.');
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) { // 10MB
                e.preventDefault();
                alert('El archivo es demasiado grande. El tamaño máximo es 10MB.');
                return;
            }
            
            // Mostrar loader
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="animate-spin">⏳</span> Procesando...';
                submitBtn.disabled = true;
            }
        });
    }
});

// Funciones del wizard
function hideWizard() {
    const wizard = document.getElementById('itemsWizard');
    if (wizard) {
        wizard.style.display = 'none';
        localStorage.setItem('bl_wizard_hidden_{{ $billOfLading->id }}', 'true');
    }
}

function downloadTemplate() {
    window.location.href = "{{ route('company.bills-of-lading.template', $billOfLading) }}";
}

function showFormatInfo() {
    const modalContent = `
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="format-modal">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">📋 Formato de Importación</h3>
                        <button onclick="closeFormatModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-medium text-blue-900 mb-2">Campos Obligatorios:</h4>
                            <ul class="text-sm text-blue-700 space-y-1">
                                <li>• <strong>item_description:</strong> Descripción detallada del item</li>
                                <li>• <strong>cargo_type_id:</strong> ID o nombre del tipo de carga</li>
                                <li>• <strong>packaging_type_id:</strong> ID o nombre del tipo de embalaje</li>
                                <li>• <strong>package_quantity:</strong> Cantidad de bultos (número entero)</li>
                                <li>• <strong>gross_weight_kg:</strong> Peso bruto en kilogramos</li>
                                <li>• <strong>country_of_origin:</strong> País de origen (código ISO 2 letras)</li>
                            </ul>
                        </div>
                        
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-medium text-green-900 mb-2">Campos Opcionales:</h4>
                            <ul class="text-sm text-green-700 space-y-1">
                                <li>• <strong>item_reference:</strong> Referencia del item</li>
                                <li>• <strong>net_weight_kg:</strong> Peso neto (se calcula automáticamente)</li>
                                <li>• <strong>volume_m3:</strong> Volumen en metros cúbicos</li>
                                <li>• <strong>declared_value:</strong> Valor declarado</li>
                                <li>• <strong>cargo_marks:</strong> Marcas de la mercadería</li>
                                <li>• <strong>commodity_code:</strong> Código NCM/HS</li>
                            </ul>
                        </div>
                        
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <h4 class="font-medium text-yellow-900 mb-2">Valores Booleanos:</h4>
                            <p class="text-sm text-yellow-700">Para campos como is_dangerous_goods, is_perishable, etc. use:</p>
                            <p class="text-sm text-yellow-700 font-mono">1, true, yes, sí, s = Verdadero</p>
                            <p class="text-sm text-yellow-700 font-mono">0, false, no, n = Falso</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button onclick="closeFormatModal()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Entendido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalContent);
}

function closeFormatModal() {
    const modal = document.getElementById('format-modal');
    if (modal) {
        modal.remove();
    }
}

// Verificar si el wizard fue ocultado previamente
document.addEventListener('DOMContentLoaded', function() {
    const wizardHidden = localStorage.getItem('bl_wizard_hidden_{{ $billOfLading->id }}');
    if (wizardHidden === 'true') {
        const wizard = document.getElementById('itemsWizard');
        if (wizard) {
            wizard.style.display = 'none';
        }
    }
});
</script>
</x-app-layout>