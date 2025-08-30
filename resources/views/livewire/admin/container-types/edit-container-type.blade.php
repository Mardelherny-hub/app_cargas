<div>
    <div>
    <div class="py-6">
        <form wire:submit.prevent="save">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                {{-- Mensajes flash --}}
                @if (session()->has('success'))
                    <div class="rounded-md bg-green-50 p-3 text-green-700 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="rounded-md bg-red-50 p-3 text-red-700 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                {{-- Header con título --}}
                <div class="bg-white shadow-sm rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Editar Tipo de Contenedor</h1>
                            <p class="mt-1 text-sm text-gray-600">
                                Modificar los parámetros del tipo de contenedor: 
                                <span class="font-semibold">{{ $containerType->name ?? $containerType->code }}</span>
                            </p>
                            <p class="mt-2 text-xs text-gray-500">Los campos marcados con <span class="text-red-500">*</span> son obligatorios</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                ID: {{ $containerType->id }}
                            </span>
                            @if($containerType->active)
                                <span class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                    Activo
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1.5 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">
                                    Inactivo
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Información básica --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4 {{ $errors->hasAny(['form.code', 'form.name', 'form.short_name', 'form.category', 'form.iso_code', 'form.description']) ? 'ring-2 ring-red-200' : '' }}">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 flex items-center gap-2">
                        Información Básica
                        @if($errors->hasAny(['form.code', 'form.name', 'form.short_name', 'form.category', 'form.iso_code', 'form.description']))
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                {{ $errors->count() }} error(es)
                            </span>
                        @endif
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @include('livewire.admin.container-types.partials.field', ['field' => 'code', 'colSpan' => 'col-span-1', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'name', 'colSpan' => 'col-span-2', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'short_name', 'colSpan' => 'col-span-1', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'category', 'colSpan' => 'col-span-1', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'iso_code', 'colSpan' => 'col-span-1', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'description', 'colSpan' => 'col-span-3', 'type' => 'textarea', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Dimensiones --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Dimensiones
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                        {{-- Dimensiones en pies (más compactas) --}}
                        <div class="col-span-2 md:col-span-6">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Dimensiones en pies</h4>
                            <div class="grid grid-cols-3 gap-3">
                                @include('livewire.admin.container-types.partials.field', ['field' => 'length_feet', 'colSpan' => '', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'width_feet', 'colSpan' => '', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'height_feet', 'colSpan' => '', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            </div>
                        </div>

                        {{-- Dimensiones externas en mm --}}
                        <div class="col-span-2 md:col-span-3">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Dimensiones externas (mm)</h4>
                            <div class="space-y-3">
                                @include('livewire.admin.container-types.partials.field', ['field' => 'length_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'width_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'height_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            </div>
                        </div>

                        {{-- Dimensiones internas en mm --}}
                        <div class="col-span-2 md:col-span-3">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Dimensiones internas (mm)</h4>
                            <div class="space-y-3">
                                @include('livewire.admin.container-types.partials.field', ['field' => 'internal_length_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'internal_width_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'internal_height_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Pesos y Volúmenes --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Pesos y Volúmenes
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Especificaciones de peso (kg)</h4>
                            <div class="space-y-3">
                                @include('livewire.admin.container-types.partials.field', ['field' => 'tare_weight_kg', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'max_gross_weight_kg', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'max_payload_kg', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Volúmenes (m³)</h4>
                            <div class="space-y-3">
                                @include('livewire.admin.container-types.partials.field', ['field' => 'internal_volume_m3', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                                @include('livewire.admin.container-types.partials.field', ['field' => 'loading_volume_m3', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Características especiales --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Características Especiales
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach(['is_refrigerated', 'is_heated', 'is_insulated', 'is_ventilated', 'has_electrical_supply', 'has_roof', 'has_sidewalls', 'has_end_walls'] as $field)
                            @if(in_array($field, $columns))
                                @include('livewire.admin.container-types.partials.field', ['field' => $field, 'type' => 'checkbox', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Configuración de puertas --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Configuración de Puertas
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        @include('livewire.admin.container-types.partials.field', ['field' => 'has_doors', 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'door_type', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'door_width_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'door_height_mm', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Control de temperatura --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Control de Temperatura
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @include('livewire.admin.container-types.partials.field', ['field' => 'min_temperature_celsius', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'max_temperature_celsius', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'has_humidity_control', 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'has_atmosphere_control', 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Compatibilidad de carga --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Compatibilidad de Carga
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach(['suitable_for_dangerous_goods', 'suitable_for_food', 'suitable_for_chemicals', 'suitable_for_liquids', 'suitable_for_bulk_cargo', 'suitable_for_heavy_cargo'] as $field)
                            @if(in_array($field, $columns))
                                @include('livewire.admin.container-types.partials.field', ['field' => $field, 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Datos operacionales --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Datos Operacionales
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @include('livewire.admin.container-types.partials.field', ['field' => 'stackable', 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'max_stack_height', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'typical_lifespan_years', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'inspection_interval_months', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Datos económicos --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Datos Económicos
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @include('livewire.admin.container-types.partials.field', ['field' => 'daily_rental_rate', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'purchase_price_estimate', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'maintenance_cost_per_year', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Estado y visualización --}}
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Estado y Visualización
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach(['active', 'is_standard', 'is_common', 'is_specialized'] as $field)
                            @if(in_array($field, $columns))
                                @include('livewire.admin.container-types.partials.field', ['field' => $field, 'type' => 'checkbox', 'requiredColumns' => $requiredColumns])
                            @endif
                        @endforeach
                        @include('livewire.admin.container-types.partials.field', ['field' => 'display_order', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                        @include('livewire.admin.container-types.partials.field', ['field' => 'color_code', 'size' => 'sm', 'requiredColumns' => $requiredColumns])
                    </div>
                </div>

                {{-- Campos restantes no categorizados --}}
                @php
                    $categorizedFields = [
                        'code', 'name', 'short_name', 'category', 'iso_code', 'description',
                        'length_feet', 'width_feet', 'height_feet', 'length_mm', 'width_mm', 'height_mm',
                        'internal_length_mm', 'internal_width_mm', 'internal_height_mm',
                        'tare_weight_kg', 'max_gross_weight_kg', 'max_payload_kg', 'internal_volume_m3', 'loading_volume_m3',
                        'is_refrigerated', 'is_heated', 'is_insulated', 'is_ventilated', 'has_electrical_supply',
                        'has_roof', 'has_sidewalls', 'has_end_walls', 'has_doors', 'door_type', 'door_width_mm', 'door_height_mm',
                        'min_temperature_celsius', 'max_temperature_celsius', 'has_humidity_control', 'has_atmosphere_control',
                        'suitable_for_dangerous_goods', 'suitable_for_food', 'suitable_for_chemicals',
                        'suitable_for_liquids', 'suitable_for_bulk_cargo', 'suitable_for_heavy_cargo',
                        'stackable', 'max_stack_height', 'typical_lifespan_years', 'inspection_interval_months',
                        'daily_rental_rate', 'purchase_price_estimate', 'maintenance_cost_per_year',
                        'active', 'is_standard', 'is_common', 'is_specialized', 'display_order', 'color_code'
                    ];
                    $remainingFields = array_diff($columns, $categorizedFields);
                @endphp

                @if(!empty($remainingFields))
                <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
                    <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                        Otros Campos
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($remainingFields as $field)
                            @include('livewire.admin.container-types.partials.field', ['field' => $field, 'requiredColumns' => $requiredColumns])
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Botonera --}}
                <div class="bg-white shadow-sm rounded-xl p-4 flex items-center justify-between sticky bottom-0 z-10">
                    <div class="flex items-center gap-2">
                        {{-- Botón eliminar con confirmación --}}
                        <button type="button" onclick="confirmDelete()"
                                class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors">
                            🗑️ Eliminar
                        </button>
                        
                        <div class="text-xs text-gray-500">
                            Creado: {{ $containerType->created_at?->format('d/m/Y H:i') }}
                            @if($containerType->updated_at && $containerType->updated_at != $containerType->created_at)
                                | Modificado: {{ $containerType->updated_at->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.container-types.index') }}"
                           class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="px-6 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                            💾 Actualizar Tipo de Contenedor
                        </button>
                    </div>
                </div>

            </div>
        </form>

        {{-- Modal de confirmación para eliminar --}}
        <div id="deleteModal" class="fixed inset-0 z-50 hidden">
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl p-6 max-w-md w-full">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Confirmar Eliminación</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        ¿Está seguro que desea eliminar el tipo de contenedor 
                        <span class="font-semibold">{{ $containerType->name ?? $containerType->code }}</span>?
                        <br><br>
                        <span class="text-red-600">Esta acción no se puede deshacer.</span>
                    </p>
                    <div class="flex justify-end gap-3">
                        <button onclick="closeDeleteModal()" 
                                class="px-4 py-2 rounded-md border text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button wire:click="delete" onclick="closeDeleteModal()"
                                class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- JavaScript para scroll automático a errores y modal --}}
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('scroll-to-error', () => {
                    // Buscar el primer elemento con error
                    const firstError = document.querySelector('.text-red-600');
                    if (firstError) {
                        // Buscar el input/campo asociado
                        const fieldContainer = firstError.closest('div');
                        const input = fieldContainer.querySelector('input, textarea, select');
                        
                        if (input) {
                            // Scroll al campo con un offset para que no quede pegado al top
                            input.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                            
                            // Focus en el campo después del scroll
                            setTimeout(() => {
                                input.focus();
                            }, 500);
                        } else {
                            // Si no encuentra input, hacer scroll al error
                            firstError.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                        }
                    }
                });
            });

            // También manejar errores de validación en tiempo real
            document.addEventListener('livewire:validated', (event) => {
                if (event.detail.errors && Object.keys(event.detail.errors).length > 0) {
                    // Pequeño delay para que se rendericen los errores
                    setTimeout(() => {
                        const firstError = document.querySelector('.text-red-600');
                        if (firstError) {
                            const fieldContainer = firstError.closest('div');
                            const input = fieldContainer.querySelector('input, textarea, select');
                            
                            if (input) {
                                input.scrollIntoView({ 
                                    behavior: 'smooth', 
                                    block: 'center' 
                                });
                                setTimeout(() => {
                                    input.focus();
                                }, 500);
                            }
                        }
                    }, 100);
                }
            });

            // Funciones para modal de confirmación
            function confirmDelete() {
                document.getElementById('deleteModal').classList.remove('hidden');
            }

            function closeDeleteModal() {
                document.getElementById('deleteModal').classList.add('hidden');
            }

            // Cerrar modal con Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeDeleteModal();
                }
            });
        </script>
    </div>
</div>
</div>
