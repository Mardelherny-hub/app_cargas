<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-bold text-gray-900">Resultado de la búsqueda</h1>
        <p class="mt-1 text-sm text-gray-600">{{ $company->legal_name }}</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5 px-4 py-8">
        <div class="flex flex-wrap gap-4 text-sm font-semibold">
            <a href="{{ route('company.webservices.query') }}" class="text-indigo-700 underline">
                Nueva búsqueda
            </a>
            <a href="{{ route('company.webservices.history') }}" class="text-indigo-700 underline">
                Volver al historial
            </a>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                 aria-label="Resultado registrado">
            <h2 class="text-lg font-semibold text-gray-900">Transacción {{ $transaction->transaction_id }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                Esta información proviene del historial de la aplicación. No representa una
                consulta en línea a Aduana.
            </p>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase text-gray-600">Servicio</dt>
                    <dd class="mt-1 text-sm font-medium text-gray-900">{{ $transaction->webservice_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-gray-600">Estado registrado</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-900">
                        @switch($transaction->status)
                            @case('success') Aceptado @break
                            @case('error') Rechazado / error @break
                            @case('sending') Envío pendiente de verificación @break
                            @case('pending') Pendiente @break
                            @default {{ $transaction->status }}
                        @endswitch
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-gray-600">Fecha</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        {{ $transaction->created_at?->format('d/m/Y H:i') ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase text-gray-600">Código informado por Aduana</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $transaction->error_code ?: '—' }}</dd>
                </div>
            </dl>

            @if($transaction->error_message)
                <div role="alert" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">Observación registrada</p>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm text-amber-900">
                        {{ $transaction->error_message }}
                    </p>
                </div>
            @endif

            @if(in_array($transaction->status, ['pending', 'sending'], true))
                <p class="mt-5 text-sm font-semibold text-red-800">
                    No reenvíes esta transacción sin comprobar su resultado en Aduana.
                </p>
            @endif
        </section>
    </div>
</x-app-layout>
