<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Adjuntos del Conocimiento
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    BL {{ $billOfLading->bill_number }}
                </p>
            </div>

            <a href="{{ route('company.bills-of-lading.show', $billOfLading) }}"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Volver al conocimiento
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 p-4">
                    <ul class="list-disc pl-5 text-sm text-red-800 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Subir documento</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        PDF, Word, Excel o imagen. Máximo 10 MB por archivo.
                    </p>
                </div>

                <form method="POST"
                      action="{{ route('company.bills-of-lading.upload-attachment', $billOfLading) }}"
                      enctype="multipart/form-data"
                      class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label for="file" class="block text-sm font-medium text-gray-700">
                            Archivo
                        </label>
                        <input id="file"
                               name="file"
                               type="file"
                               required
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                               class="mt-1 block w-full text-sm text-gray-700 border border-gray-300 rounded-md p-2">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">
                            Descripción
                        </label>
                        <input id="description"
                               name="description"
                               type="text"
                               maxlength="255"
                               value="{{ old('description') }}"
                               placeholder="Ej.: Factura comercial, conocimiento firmado, certificado"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-blue-700">
                            Subir archivo
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Documentos adjuntos</h3>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $attachments->count() }} {{ $attachments->count() === 1 ? 'archivo' : 'archivos' }}
                        </p>
                    </div>
                </div>

                @forelse($attachments as $attachment)
                    <div class="px-6 py-4 border-b border-gray-100 last:border-b-0 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 break-all">
                                {{ $attachment->original_filename }}
                            </p>

                            @if($attachment->description)
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ $attachment->description }}
                                </p>
                            @endif

                            <p class="text-xs text-gray-500 mt-2">
                                {{ number_format($attachment->file_size_bytes / 1024, 1, ',', '.') }} KB
                                · {{ strtoupper($attachment->file_extension) }}
                                @if($attachment->created_at)
                                    · {{ $attachment->created_at->format('d/m/Y H:i') }}
                                @endif
                            </p>
                        </div>

                        <form method="POST"
                              action="{{ route('company.bills-of-lading.delete-attachment', [$billOfLading, $attachment->id]) }}"
                              onsubmit="return confirm('¿Eliminar este archivo adjunto?');"
                              class="flex-shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                                Eliminar
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-sm text-gray-500">
                        Este conocimiento todavía no tiene documentos adjuntos.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
