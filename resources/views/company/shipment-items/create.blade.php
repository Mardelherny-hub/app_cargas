<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Agregar Item al Shipment') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Shipment: <span class="font-medium">{{ $shipment->shipment_number }}</span> - 
                    Viaje: <span class="font-medium">{{ $shipment->voyage->voyage_number }}</span>
                </p>
            </div>
            <a href="{{ route('company.shipments.show', $shipment) }}" 
               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Volver al Shipment
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @livewire('shipment-item-create-form', [
                'shipment' => $shipment,
                'billOfLading' => $billOfLading,
                'needsToCreateBL' => $needsToCreateBL,
                'defaultBLData' => $defaultBLData,
                'cargoTypes' => $cargoTypes,
                'packagingTypes' => $packagingTypes,
                'clients' => $clients,
                'ports' => $ports,
                'countries' => $countries,
                'containerTypes' => $containerTypes,
                'nextLineNumber' => $nextLineNumber
            ], key('shipment-item-form-' . $shipment->id . '-' . ($billOfLading ? $billOfLading->id : 'new') . '-' . uniqid()))
        </div>
    </div>

    @push('scripts')
    <script>
        console.log('🔵 Script UX cargado en create.blade.php');
        
        document.addEventListener('livewire:initialized', () => {
            console.log('🟢 Livewire initialized');
            
            // ✅ 1. Scroll al tope cuando se crea un item exitosamente
            Livewire.on('item-created', () => {
                console.log('🟢 Evento item-created recibido');
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // ✅ 2. Scroll al primer error de validación
            Livewire.on('scroll-to-error', () => {
                console.log('🔴 Evento scroll-to-error recibido');
                setTimeout(() => {
                    // Buscar primero el input/select/textarea con borde rojo
                    let targetElement = document.querySelector('input.border-red-300, select.border-red-300, textarea.border-red-300');
                    
                    // Si no hay input con error, buscar el mensaje de error y subir al campo
                    if (!targetElement) {
                        const errorMessage = document.querySelector('p.text-sm.text-red-600');
                        if (errorMessage) {
                            // Buscar el input/select/textarea anterior al mensaje de error
                            const container = errorMessage.closest('div');
                            if (container) {
                                targetElement = container.querySelector('input, select, textarea');
                            }
                        }
                    }
                    
                    console.log('Elemento con error:', targetElement);
                    
                    if (targetElement) {
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        // Hacer focus en el campo con error
                        targetElement.focus();
                    }
                }, 100);
            });
        });

        console.log('🔵 Función confirmFinish definida');
        
        // ✅ 3. Confirmación al hacer clic en "Terminar" con datos sin guardar
        window.confirmFinish = function(event, shipmentId) {
            console.log('🟡 confirmFinish llamada');
            const hasData = checkFormHasData();
            console.log('¿Tiene datos?', hasData);
            
            if (hasData) {
                event.preventDefault();
                
                if (confirm('⚠️ Tiene datos sin guardar en el formulario.\n\n¿Está seguro que desea salir? Los datos se perderán.')) {
                    window.location.href = '/company/shipments/' + shipmentId;
                }
            }
        };

        function checkFormHasData() {
            console.log('🔍 Verificando datos en formulario...');
            
            // Buscar todos los inputs de texto y numéricos (excepto disabled/readonly)
            const inputs = document.querySelectorAll('input[type="text"], input[type="number"], textarea');
            console.log('Total inputs encontrados:', inputs.length);
            
            for (let input of inputs) {
                // Saltar si está disabled o readonly
                if (input.disabled || input.readOnly) {
                    continue;
                }
                
                const value = input.value.trim();
                const wireModel = input.getAttribute('wire:model') || input.getAttribute('wire:model.live') || '';
                
                console.log('Verificando:', wireModel || input.name || input.id, '| Valor:', value);
                
                // Si tiene contenido significativo (no vacío, no "1" por defecto)
                if (value !== '' && value !== '1' && value !== '0') {
                    console.log('✅ DATO ENCONTRADO:', wireModel || input.name, '=', value);
                    return true;
                }
            }

            // Verificar selects con valor seleccionado
            const selects = document.querySelectorAll('select');
            console.log('Total selects encontrados:', selects.length);
            
            for (let select of selects) {
                if (select.disabled) continue;
                
                const wireModel = select.getAttribute('wire:model') || select.getAttribute('wire:model.live') || '';
                
                if (select.value !== '' && select.value !== null) {
                    console.log('✅ SELECT CON VALOR:', wireModel || select.name, '=', select.value);
                    return true;
                }
            }

            // Verificar checkboxes marcados (excepto los por defecto)
            const checkboxes = document.querySelectorAll('input[type="checkbox"]:checked');
            let significantCheckboxes = 0;
            
            for (let checkbox of checkboxes) {
                if (checkbox.disabled) continue;
                
                const wireModel = checkbox.getAttribute('wire:model') || checkbox.getAttribute('wire:model.live') || '';
                
                // Ignorar checkboxes que son false por defecto y están desmarcados
                if (wireModel && !wireModel.includes('continueAdding')) {
                    significantCheckboxes++;
                    console.log('✅ CHECKBOX MARCADO:', wireModel);
                }
            }
            
            if (significantCheckboxes > 0) {
                return true;
            }

            console.log('❌ No se encontraron datos significativos');
            return false;
        }
    </script>
    @endpush

    </x-app-layout>