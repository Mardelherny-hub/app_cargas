<div>
    <div>
    {{-- Mensajes de estado --}}
    @if (session()->has('success'))
        <div class="rounded-lg bg-green-50 p-3 text-green-700 text-sm mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-lg bg-red-50 p-3 text-red-700 text-sm mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Formulario Principal --}}
    <div class="bg-white rounded-xl shadow-sm">
        <form wire:submit.prevent="save" class="space-y-6 p-6">
            
            {{-- Header del formulario --}}
            <div class="border-b border-gray-100 pb-4">
                <h3 class="text-lg font-semibold text-gray-900">Información del Tipo de Packaging</h3>
                <p class="text-sm text-gray-500 mt-1">Configure los datos del tipo de packaging. Los campos marcados con * son obligatorios.</p>
            </div>

            {{-- Grid de campos principales --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                
                {{-- Campos dinámicos basados en $columns --}}
                @foreach($columns as $column)
                    <div class="flex flex-col">
                        <label for="{{ $column }}" class="text-sm font-medium text-gray-700 mb-1">
                            {{ ucwords(str_replace('_', ' ', $column)) }}
                            @if(in_array($column, ['code', 'name']))
                                <span class="text-red-500">*</span>
                            @endif
                        </label>

                        {{-- Campo de tipo texto/string --}}
                        @if(in_array($column, ['code', 'name', 'short_name', 'unece_code', 'iso_code', 'imdg_code', 'category', 'material_type', 'closure_type', 'seal_type', 'valve_type', 'opening_mechanism', 'dispensing_method', 'protection_level', 'argentina_ws_code', 'paraguay_ws_code', 'customs_code', 'senasa_code', 'icon', 'color_code']))
                            <input 
                                type="text" 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Ingrese {{ strtolower(str_replace('_', ' ', $column)) }}"
                            >

                        {{-- Campo textarea --}}
                        @elseif(in_array($column, ['description', 'barrier_properties']))
                            <textarea 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                rows="3"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Ingrese {{ strtolower(str_replace('_', ' ', $column)) }}"
                            ></textarea>

                        {{-- Campos numéricos --}}
                        @elseif(in_array($column, ['length_mm', 'width_mm', 'height_mm', 'diameter_mm', 'volume_liters', 'volume_m3', 'empty_weight_kg', 'max_gross_weight_kg', 'max_net_weight_kg', 'weight_tolerance_percent', 'max_stack_height', 'stacking_weight_limit_kg', 'temperature_range_min', 'temperature_range_max', 'testing_frequency_days', 'acceptable_defect_rate_percent', 'typical_lead_time_days', 'display_order']))
                            <input 
                                type="number" 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                step="0.01"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="0"
                            >

                        {{-- Campos de checkbox/boolean --}}
                        @elseif(in_array($column, ['is_stackable', 'is_reusable', 'is_returnable', 'is_collapsible', 'requires_palletizing', 'requires_strapping', 'requires_wrapping', 'requires_special_handling', 'is_weatherproof', 'is_moisture_resistant', 'is_food_grade', 'suitable_for_food', 'suitable_for_dangerous_goods', 'suitable_for_liquids', 'suitable_for_gases', 'suitable_for_solids', 'suitable_for_powders', 'suitable_for_chemicals', 'suitable_for_pharmaceuticals', 'suitable_for_electronics', 'suitable_for_textiles', 'suitable_for_automotive', 'requires_labeling', 'allows_printing', 'requires_hazmat_marking', 'requires_testing', 'widely_available', 'active', 'is_standard', 'is_common', 'is_specialized', 'is_deprecated']))
                            <div class="flex items-center h-5">
                                <input 
                                    type="checkbox" 
                                    id="{{ $column }}" 
                                    wire:model="{{ $column }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <label for="{{ $column }}" class="ml-2 text-sm text-gray-600">
                                    {{ in_array($column, ['active']) ? 'Activo' : 'Sí' }}
                                </label>
                            </div>

                        {{-- Campos de arrays/JSON --}}
                        @elseif(in_array($column, ['handling_equipment', 'certifications', 'regulatory_compliance', 'required_markings', 'prohibited_markings', 'webservice_mapping', 'industry_applications', 'commodity_compatibility', 'seasonal_considerations', 'quality_standards', 'preferred_suppliers', 'alternative_types']))
                            <textarea 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                rows="2"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                                placeholder='["item1", "item2"] o {"key": "value"}'
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">Formato JSON válido</p>

                        {{-- Campo select para dispensing_method --}}
                        @elseif($column === 'dispensing_method')
                            <select 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            >
                                <option value="">Seleccione...</option>
                                <option value="pour">Verter</option>
                                <option value="spray">Rociar</option>
                                <option value="pump">Bomba</option>
                                <option value="tap">Grifo</option>
                                <option value="valve">Válvula</option>
                            </select>

                        {{-- Campo por defecto --}}
                        @else
                            <input 
                                type="text" 
                                id="{{ $column }}" 
                                wire:model="{{ $column }}"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Ingrese {{ strtolower(str_replace('_', ' ', $column)) }}"
                            >
                        @endif

                        {{-- Mostrar errores --}}
                        @error($column)
                            <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                        @enderror
                    </div>
                @endforeach
            </div>

            {{-- Botones de acción --}}
            <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    <span class="text-red-500">*</span> Campos obligatorios
                </div>
                
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.packaging-types.index') }}" 
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Cancelar
                    </a>
                    
                    <button type="submit" 
                            wire:loading.attr="disabled"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50">
                        <span wire:loading.remove>Actualizar Tipo de Packaging</span>
                        <span wire:loading>Guardando...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Información adicional --}}
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Información sobre campos JSON</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <p>Los campos que aceptan arrays deben ingresarse en formato JSON válido:</p>
                    <ul class="list-disc list-inside mt-1 space-y-1">
                        <li><strong>Array simple:</strong> ["valor1", "valor2", "valor3"]</li>
                        <li><strong>Objeto:</strong> {"clave1": "valor1", "clave2": "valor2"}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
