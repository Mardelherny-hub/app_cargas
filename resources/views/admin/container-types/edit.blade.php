<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Editar Tipo de Contenedor
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Modificar información del tipo de contenedor existente
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.container-types.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-md text-sm font-medium">
                    Volver al Listado
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="{{ route('admin.dashboard') }}" class="text-gray-700 hover:text-gray-900">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="{{ route('admin.container-types.index') }}" class="ml-1 text-gray-700 hover:text-gray-900 md:ml-2">
                                Tipos de Contenedor
                            </a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-gray-500 md:ml-2">Editar</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Mensajes de Error -->
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Se encontraron los siguientes errores:
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Formulario Principal -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Información del Tipo de Contenedor</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Complete todos los campos requeridos para actualizar el tipo de contenedor.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.container-types.update', $item) }}" class="px-6 py-4 space-y-6">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($columns as $column)
                            @if(in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at']))
                                @continue
                            @endif

                            <div class="@if(in_array($column, ['description', 'notes', 'observations'])) md:col-span-2 @endif">
                                <label for="{{ $column }}" class="block text-sm font-medium text-gray-700 capitalize">
                                    {{ str_replace('_', ' ', $column) }}
                                    @if(in_array($column, ['name', 'code', 'iso_code']))
                                        <span class="text-red-500">*</span>
                                    @endif
                                </label>

                                @if($column === 'active')
                                    <!-- Campo Boolean para Active -->
                                    <div class="mt-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" 
                                                   name="{{ $column }}" 
                                                   id="{{ $column }}"
                                                   value="1"
                                                   {{ old($column, $item->$column) ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                            <span class="ml-2 text-sm text-gray-900">Tipo de contenedor activo</span>
                                        </label>
                                    </div>

                                @elseif(in_array($column, ['description', 'notes', 'observations', 'comments']))
                                    <!-- Campo Textarea para campos largos -->
                                    <textarea name="{{ $column }}" 
                                              id="{{ $column }}"
                                              rows="3"
                                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error($column) border-red-300 @enderror"
                                              placeholder="Ingrese {{ str_replace('_', ' ', $column) }}">{{ old($column, $item->$column) }}</textarea>

                                @elseif(str_contains($column, 'weight') || str_contains($column, 'capacity') || str_contains($column, 'volume'))
                                    <!-- Campo Numérico para pesos/capacidades -->
                                    <input type="number" 
                                           name="{{ $column }}" 
                                           id="{{ $column }}"
                                           value="{{ old($column, $item->$column) }}"
                                           step="0.01"
                                           min="0"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error($column) border-red-300 @enderror"
                                           placeholder="0.00">

                                @elseif(str_contains($column, 'order') || str_contains($column, 'priority') || str_contains($column, 'sequence'))
                                    <!-- Campo Numérico entero para orden/prioridad -->
                                    <input type="number" 
                                           name="{{ $column }}" 
                                           id="{{ $column }}"
                                           value="{{ old($column, $item->$column) }}"
                                           min="0"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error($column) border-red-300 @enderror"
                                           placeholder="0">

                                @else
                                    <!-- Campo de Texto estándar -->
                                    <input type="text" 
                                           name="{{ $column }}" 
                                           id="{{ $column }}"
                                            value="{{ old($column, is_array($item->$column) ? json_encode($item->$column) : $item->$column) }}"                                           @if(in_array($column, ['name', 'code', 'iso_code']))
                                               required
                                           @endif
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error($column) border-red-300 @enderror"
                                           placeholder="Ingrese {{ str_replace('_', ' ', $column) }}">
                                @endif

                                @error($column)
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                @if($column === 'code')
                                    <p class="mt-1 text-xs text-gray-500">
                                        Código único para identificar el tipo de contenedor (ej: 20DV, 40HC)
                                    </p>
                                @elseif($column === 'iso_code')
                                    <p class="mt-1 text-xs text-gray-500">
                                        Código ISO estándar internacional (ej: 22G1, 45G1)
                                    </p>
                                @elseif($column === 'name')
                                    <p class="mt-1 text-xs text-gray-500">
                                        Nombre descriptivo del tipo de contenedor
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <!-- Información de Auditoría -->
                    @if($item->created_at || $item->updated_at)
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Información de Auditoría</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                                @if($item->created_at)
                                    <div>
                                        <span class="font-medium">Creado:</span>
                                        {{ $item->created_at->format('d/m/Y H:i') }}
                                        ({{ $item->created_at->diffForHumans() }})
                                    </div>
                                @endif
                                @if($item->updated_at)
                                    <div>
                                        <span class="font-medium">Última actualización:</span>
                                        {{ $item->updated_at->format('d/m/Y H:i') }}
                                        ({{ $item->updated_at->diffForHumans() }})
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Botones de Acción -->
                    <div class="flex justify-end space-x-3 border-t border-gray-200 pt-6">
                        <a href="{{ route('admin.container-types.index') }}"
                           class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancelar
                        </a>
                        <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Actualizar Tipo de Contenedor
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>