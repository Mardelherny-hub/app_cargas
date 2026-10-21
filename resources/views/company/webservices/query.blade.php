<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold text-gray-900">Consultar transacción aduanera</h1>
        <p class="mt-1 text-sm text-gray-600">{{ $company->legal_name }}</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5 px-4 py-8">
        <a href="{{ route('company.webservices.history') }}"
           class="text-sm font-semibold text-indigo-700 underline">
            Volver al historial
        </a>

        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Buscar una transmisión registrada</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600">
                Esta búsqueda consulta el historial guardado en la aplicación.
                No consulta el estado en línea ante Aduana ni reenvía una transmisión.
                Si una transacción está pendiente o su resultado es incierto,
                verificá la respuesta aduanera antes de intentar otro envío.
            </p>

            @if(session('error'))
                <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <p class="font-semibold">Revisá los datos de la búsqueda:</p>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('company.webservices.process-query') }}"
                  class="mt-5 space-y-4">
                @csrf

                <div>
                    <label for="webservice_type" class="block text-sm font-semibold text-gray-800">Servicio</label>
                    <select id="webservice_type" name="webservice_type" required
                            class="mt-2 block w-full rounded-lg border-gray-300">
                        <option value="">Seleccioná el servicio</option>
                        @foreach($availableTypes as $type => $label)
                            <option value="{{ $type }}" @selected(old('webservice_type') === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="query_type" class="block text-sm font-semibold text-gray-800">Buscar por</label>
                    <select id="query_type" name="query_type" required
                            class="mt-2 block w-full rounded-lg border-gray-300">
                        <option value="transaction_id" @selected(old('query_type', 'transaction_id') === 'transaction_id')>Número de transacción</option>
                        <option value="reference" @selected(old('query_type') === 'reference')>Referencia aduanera</option>
                    </select>
                </div>

                <div>
                    <label for="query_value" class="block text-sm font-semibold text-gray-800">Número o referencia</label>
                    <input id="query_value" name="query_value" type="text" required minlength="3"
                           value="{{ old('query_value') }}"
                           placeholder="Ingresá el identificador de la transmisión"
                           class="mt-2 block min-h-11 w-full rounded-lg border-gray-300">
                </div>

                <button type="submit"
                        class="min-h-11 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                    Buscar en el historial
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
