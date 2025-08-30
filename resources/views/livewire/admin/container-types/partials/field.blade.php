{{-- 
    Parcial reutilizable para campos del formulario 
    Guarda como: resources/views/livewire/admin/container-types/partials/field.blade.php
--}}

@php
    $fieldExists = in_array($field, $columns ?? []);
    if (!$fieldExists) return;
    
    $label = \Illuminate\Support\Str::of($field)->replace('_',' ')->title();
    $type = $type ?? 'auto'; // auto, text, number, checkbox, textarea, select
    $size = $size ?? 'normal'; // sm, normal
    $colSpan = $colSpan ?? '';
    
    // Detectar tipo automáticamente si no se especifica
    if ($type === 'auto') {
        if ($isBoolean[$field] ?? false) {
            $type = 'checkbox';
        } elseif ($isDate[$field] ?? false) {
            $type = 'date';
        } elseif ($isNumeric[$field] ?? false) {
            $type = 'number';
        } elseif (preg_match('/desc|note|instruction/i', $field)) {
            $type = 'textarea';
        } else {
            $type = 'text';
        }
    }
    
    $inputClasses = $size === 'sm' 
        ? 'w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-xs' 
        : 'w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm';
    
    // Agregar clases de error si existe
    $hasError = $errors->has("form.{$field}");
    if ($hasError) {
        $inputClasses = str_replace('border-gray-300', 'border-red-300', $inputClasses);
        $inputClasses = str_replace('focus:ring-indigo-500 focus:border-indigo-500', 'focus:ring-red-500 focus:border-red-500', $inputClasses);
    }
    
    $labelClasses = $size === 'sm' 
        ? 'block text-xs font-medium text-gray-700 mb-1' 
        : 'block text-sm font-medium text-gray-700 mb-1';
    
    // Agregar clases de error al label si existe
    if ($hasError) {
        $labelClasses = str_replace('text-gray-700', 'text-red-700', $labelClasses);
    }
    
    // Verificar si el campo es requerido
    $isRequired = in_array($field, $requiredColumns ?? []);
    
    // DEBUG: mostrar info temporal
    $debugInfo = $isRequired ? "REQUIRED" : "optional";
@endphp



<div class="{{ $colSpan }}">
    @if($type === 'checkbox')
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:model="form.{{ $field }}"
                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 {{ $size === 'sm' ? 'h-3 w-3' : 'h-4 w-4' }}">
            <span class="{{ $size === 'sm' ? 'text-xs' : 'text-sm' }} text-gray-700">
                {{ $label }}
                @if($isRequired)
                    <span class="text-red-500 ml-1">*</span>
                @endif
            </span>
        </label>
    @else
        <label class="{{ $labelClasses }}">
            {{ $label }}
            @if($isRequired)
                <span class="text-red-500 ml-1">*</span>
            @endif
        </label>

        @if($type === 'textarea')
            <textarea rows="{{ $size === 'sm' ? '2' : '3' }}" wire:model.defer="form.{{ $field }}"
                      class="{{ $inputClasses }}"
                      placeholder="—"></textarea>
        
        @elseif($type === 'date')
            <input type="date" wire:model.defer="form.{{ $field }}"
                   class="{{ $inputClasses }}">
        
        @elseif($type === 'number')
            <input type="number" step="any" wire:model.defer="form.{{ $field }}"
                   class="{{ $inputClasses }}"
                   placeholder="0">
        
        @elseif($field === 'category')
            <select wire:model.defer="form.{{ $field }}" class="{{ $inputClasses }}">
                <option value="">Seleccionar...</option>
                <option value="dry_cargo">Carga Seca</option>
                <option value="refrigerated">Refrigerado</option>
                <option value="open_top">Techo Abierto</option>
                <option value="flat_rack">Plataforma</option>
                <option value="tank">Tanque</option>
                <option value="bulk">Granel</option>
                <option value="specialized">Especializado</option>
            </select>
        
        @elseif($field === 'door_type')
            <select wire:model.defer="form.{{ $field }}" class="{{ $inputClasses }}">
                <option value="">Seleccionar...</option>
                <option value="standard">Estándar</option>
                <option value="double_door">Doble Puerta</option>
                <option value="side_door">Puerta Lateral</option>
                <option value="end_door">Puerta Frontal</option>
                <option value="no_door">Sin Puerta</option>
            </select>
        
        @elseif($field === 'length_feet')
            <select wire:model.defer="form.{{ $field }}" class="{{ $inputClasses }}">
                <option value="">Seleccionar...</option>
                <option value="10">10'</option>
                <option value="20">20'</option>
                <option value="40">40'</option>
                <option value="45">45'</option>
                <option value="48">48'</option>
                <option value="53">53'</option>
            </select>
        
        @elseif($field === 'width_feet')
            <select wire:model.defer="form.{{ $field }}" class="{{ $inputClasses }}">
                <option value="">Seleccionar...</option>
                <option value="8">8'</option>
                <option value="8.5">8.5'</option>
                <option value="9">9'</option>
                <option value="9.5">9.5'</option>
            </select>
        
        @elseif($field === 'height_feet')
            <select wire:model.defer="form.{{ $field }}" class="{{ $inputClasses }}">
                <option value="">Seleccionar...</option>
                <option value="8">8'</option>
                <option value="8.5">8.5'</option>
                <option value="9">9'</option>
                <option value="9.5">9.5'</option>
                <option value="10.5">10.5'</option>
            </select>
        
        @elseif($field === 'color_code')
            <input type="color" wire:model.defer="form.{{ $field }}"
                   class="h-10 w-20 rounded border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
        
        @else
            <input type="text" wire:model.defer="form.{{ $field }}"
                   class="{{ $inputClasses }}"
                   placeholder="—">
        @endif
    @endif

    @error("form.{$field}")
        <p class="mt-1 {{ $size === 'sm' ? 'text-xs' : 'text-sm' }} text-red-600">{{ $message }}</p>
    @enderror
</div>