<x-app-layout>
    <x-slot name="header">
        @include('company.simple.partials.afip-header', [
            'voyage' => $voyage,
            'company' => $company,
            'active' => 'desconsolidado',
        ])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded text-sm text-yellow-800">
                    <strong>AFIP respondió con advertencias:</strong> {{ session('warning') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded">
                    <ul class="list-disc list-inside text-sm text-red-800 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Identificador viaje AFIP</p>
                            <p class="mt-1 font-semibold text-gray-900">
                                {{ $voyage->argentina_voyage_id ?: 'No informado' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Títulos desconsolidados</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">
                                {{ $desconsolidatedBillsCount }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Contenedores vinculados</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900">
                                {{ $containersCount }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Prevalidación</p>
                            <p class="mt-1">
                                @if(!empty($validation['errors']))
                                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                        Con errores
                                    </span>
                                @elseif(!empty($validation['warnings']))
                                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        Con advertencias
                                    </span>
                                @else
                                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        Lista
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @if(!empty($validation['errors']) || !empty($validation['warnings']))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        @if(!empty($validation['errors']))
                            <div class="mb-4">
                                <h3 class="font-medium text-red-800 mb-2">Errores previos al envío</h3>
                                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                    @foreach($validation['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($validation['warnings']))
                            <div>
                                <h3 class="font-medium text-yellow-800 mb-2">Advertencias</h3>
                                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                    @foreach($validation['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($customsContainers->isNotEmpty())
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <div class="mb-5">
                            <h3 class="text-lg font-medium text-gray-900">Datos aduaneros de contenedores</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Estos campos se usan exclusivamente para ATA-DESC. No modifican tara, peso,
                                estado operativo, tarifas, ubicación, precintos ni otros datos internos de gestión.
                            </p>
                        </div>

                        <div class="space-y-5">
                            @foreach($customsContainers as $customsRow)
                                @php
                                    $container = $customsRow['container'];
                                    $items = $customsRow['items'];
                                    $allConditionsReady = collect($items)->every(
                                        fn ($item) => in_array($item['condition'], ['H', 'P'], true)
                                    );
                                    $hasExpiry = !empty($container->csc_expiry_date);
                                    $hasAcep = !empty($container->acep);
                                    $documentReady = $hasExpiry !== $hasAcep;
                                    $containerReady = $allConditionsReady && $documentReady;
                                    $isOldContainer = (int) old('container_id') === (int) $container->id;
                                    $oldItemConditions = $isOldContainer ? old('item_conditions', []) : [];
                                @endphp

                                <form
                                    method="POST"
                                    action="{{ route('company.simple.desconsolidado.container-customs', [$voyage, $container]) }}"
                                    class="border rounded-lg p-4 {{ $containerReady ? 'border-green-200 bg-green-50/30' : 'border-yellow-200 bg-yellow-50/30' }}"
                                >
                                    @csrf
                                    <input type="hidden" name="container_id" value="{{ $container->id }}">

                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-4">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h4 class="font-semibold text-gray-900">
                                                    {{ $container->container_number }}
                                                </h4>
                                                @if($containerReady)
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Datos ATA-DESC completos
                                                    </span>
                                                @else
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        Completar datos ATA-DESC
                                                    </span>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">
                                                Estado operativo actual: {{ $container->condition ?: '—' }}.
                                                Se muestra sólo como referencia y no se modifica desde aquí.
                                            </p>
                                        </div>

                                        <button
                                            type="submit"
                                            class="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                                        >
                                            Guardar datos aduaneros
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                                        <div>
                                            <p class="text-sm font-medium text-gray-700 mb-2">
                                                Condición H/P por línea
                                            </p>
                                            <div class="space-y-2">
                                                @foreach($items as $item)
                                                    @php
                                                        $selectedCondition = $isOldContainer
                                                            ? ($oldItemConditions[$item['id']] ?? $item['condition'])
                                                            : $item['condition'];
                                                    @endphp
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center">
                                                        <div class="text-sm text-gray-700 sm:col-span-2">
                                                            BL {{ $item['bill_number'] ?: '—' }} · Línea {{ $item['line_number'] ?: '—' }}
                                                        </div>
                                                        <select
                                                            name="item_conditions[{{ $item['id'] }}]"
                                                            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                            required
                                                        >
                                                            <option value="">Seleccionar H/P</option>
                                                            <option value="H" @selected($selectedCondition === 'H')>
                                                                H - Casa a Casa
                                                            </option>
                                                            <option value="P" @selected($selectedCondition === 'P')>
                                                                P - Muelle a Muelle
                                                            </option>
                                                        </select>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <p class="text-sm font-medium text-gray-700 mb-2">
                                                Documento del contenedor
                                            </p>
                                            <p class="text-xs text-gray-500 mb-3">
                                                ATA-DESC exige Fecha de vencimiento del contenedor o ACEP. Informe uno u otro dato real, no ambos; no se completa automáticamente.
                                            </p>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                                        Vencimiento CSC
                                                    </label>
                                                    <input
                                                        type="date"
                                                        name="csc_expiry_date"
                                                        value="{{ $isOldContainer ? old('csc_expiry_date', $container->csc_expiry_date?->format('Y-m-d')) : $container->csc_expiry_date?->format('Y-m-d') }}"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                    >
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                                        ACEP
                                                    </label>
                                                    <input
                                                        type="text"
                                                        name="acep"
                                                        maxlength="20"
                                                        value="{{ $isOldContainer ? old('acep', $container->acep) : $container->acep }}"
                                                        class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                                        placeholder="Hasta 20 caracteres"
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('company.simple.desconsolidado.send', $voyage) }}">
                    @csrf

                    <div class="p-6 border-b border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-5">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Títulos a transmitir</h3>
                                <p class="text-sm text-gray-600 mt-1">
                                    Seleccione exactamente los conocimientos que desea procesar. El servidor vuelve a validar
                                    pertenencia, estado y datos obligatorios de los títulos seleccionados antes de transmitir a AFIP.
                                </p>
                            </div>
                            <div class="text-xs text-gray-500 md:text-right">
                                <div><strong>Sin registrar / Eliminado:</strong> puede Registrar</div>
                                <div><strong>Registrado / Rectificado:</strong> puede Rectificar o Eliminar</div>
                            </div>
                        </div>

                        @if($desconsolidatedBills->isEmpty())
                            <div class="bg-yellow-50 border border-yellow-200 rounded p-4 text-sm text-yellow-800">
                                Este viaje no tiene conocimientos con <code>master_bill_number</code> informado.
                            </div>
                        @else
                            <div class="overflow-x-auto border rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Seleccionar
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Conocimiento hijo
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Título madre
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Embarque
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Estado AFIP
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Operaciones válidas
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($desconsolidatedBills as $bill)
                                            @php
                                                $state = $billStates[(int) $bill->id] ?? null;
                                                $isActive = in_array($state, ['registrar', 'rectificar'], true);
                                                $stateLabel = match($state) {
                                                    'registrar' => 'Registrado',
                                                    'rectificar' => 'Rectificado',
                                                    'eliminar' => 'Eliminado',
                                                    default => 'Sin registrar',
                                                };
                                                $stateClass = match($state) {
                                                    'registrar' => 'bg-blue-100 text-blue-800',
                                                    'rectificar' => 'bg-green-100 text-green-800',
                                                    'eliminar' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800',
                                                };
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <input
                                                        type="checkbox"
                                                        name="bill_ids[]"
                                                        value="{{ $bill->id }}"
                                                        @checked(in_array($bill->id, old('bill_ids', [])))
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                    >
                                                </td>
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                                    {{ $bill->bill_number ?: '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $bill->master_bill_number ?: '—' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                    {{ $bill->loadingPort?->code ?: '—' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $stateClass }}">
                                                        {{ $stateLabel }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-xs text-gray-700">
                                                    @if($isActive)
                                                        <span class="font-medium text-yellow-700">Rectificar</span>
                                                        <span class="text-gray-400 mx-1">·</span>
                                                        <span class="font-medium text-red-700">Eliminar</span>
                                                    @else
                                                        <span class="font-medium text-blue-700">Registrar</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="mt-5 flex flex-col sm:flex-row gap-3">
                            <button
                                type="submit"
                                name="action"
                                value="registrar"
                                @disabled($desconsolidatedBills->isEmpty())
                                class="inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Registrar seleccionados
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="rectificar"
                                @disabled($desconsolidatedBills->isEmpty())
                                class="inline-flex justify-center items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Rectificar seleccionados
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="eliminar"
                                @disabled($desconsolidatedBills->isEmpty())
                                onclick="return confirm('Esta acción solicitará a AFIP la eliminación únicamente de los títulos seleccionados. ¿Desea continuar?')"
                                class="inline-flex justify-center items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Eliminar seleccionados
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Historial de transacciones</h3>

                    @if($transactions->isEmpty())
                        <p class="text-sm text-gray-500">Todavía no hay transacciones de Desconsolidados Argentina para este viaje.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operación</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Títulos</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IdTransaccion</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($transactions as $transaction)
                                        @php
                                            $metadata = $transaction->additional_metadata ?? [];
                                            $method = $metadata['method'] ?? '—';
                                            $billNumbers = $metadata['bill_numbers'] ?? [];
                                        @endphp
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap">
                                                {{ $transaction->created_at?->format('d/m/Y H:i:s') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-900 capitalize">
                                                {{ $method }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700">
                                                {{ $billNumbers ? implode(', ', $billNumbers) : 'Sin detalle por título' }}
                                            </td>
                                            <td class="px-4 py-3 text-xs font-mono text-gray-700 whitespace-nowrap">
                                                {{ $transaction->transaction_id }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if($transaction->status === 'success')
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Aceptada</span>
                                                @elseif($transaction->status === 'error')
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Error</span>
                                                @else
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        {{ $transaction->status }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-gray-700 max-w-md">
                                                @if($transaction->status === 'success')
                                                    {{ $transaction->confirmation_number ?: 'Aceptada por AFIP' }}
                                                @else
                                                    {{ $transaction->error_message ?: '—' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
