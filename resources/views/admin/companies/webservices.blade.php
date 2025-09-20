<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Webservices -') }} {{ $company->commercial_name ?: $company->legal_name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ __('Configuración de webservices aduaneros') }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.companies.show', $company) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Volver a Empresa') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="flex-shrink-0 h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <svg class="flex-shrink-0 h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Información General -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Información General') }}</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Empresa') }}</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $company->legal_name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Identificación Fiscal') }}</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $company->tax_id }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('País') }}</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $company->country }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuración General -->
            <form method="POST" action="{{ route('admin.companies.update-webservices', $company) }}">
                @csrf
                @method('PUT')

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">{{ __('Configuración General') }}</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="ws_environment" class="block text-sm font-medium text-gray-700">
                                    {{ __('Ambiente') }}
                                </label>
                                <select id="ws_environment" name="ws_environment" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="testing" {{ ($company->ws_environment ?? 'testing') === 'testing' ? 'selected' : '' }}>
                                        {{ __('Testing (Homologación)') }}
                                    </option>
                                    <option value="production" {{ ($company->ws_environment ?? 'testing') === 'production' ? 'selected' : '' }}>
                                        {{ __('Production (Producción)') }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Roles Habilitados') }}</label>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    @if($company->company_roles)
                                        @foreach($company->company_roles as $role)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                {{ $role }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-sm text-gray-500">{{ __('Sin roles configurados') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Argentina - AFIP -->
                @if($webserviceConfig['argentina']['enabled'])
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 bg-blue-50 border-b border-blue-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🇦🇷 {{ __('Argentina - AFIP') }}
                                </h3>
                                <div class="flex space-x-2">
                                    @foreach($availableWebservices['argentina'] ?? [] as $key => $name)
                                        <button type="button" 
                                                onclick="testWebservice('{{ $key }}', 'AR')"
                                                class="bg-blue-500 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">
                                            {{ __('Test') }} {{ $key }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('CUIT') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $webserviceConfig['argentina']['cuit'] }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Estado Certificado') }}</label>
                                    <div class="mt-1">
                                        @if($webserviceConfig['argentina']['certificate_path'])
                                            @if($certificateStatus['expired'])
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                    {{ __('Expirado') }}
                                                </span>
                                            @elseif($certificateStatus['expires_soon'])
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    {{ __('Próximo a expirar') }}
                                                </span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                    {{ __('Válido') }}
                                                </span>
                                            @endif
                                            @if($webserviceConfig['argentina']['certificate_expires_at'])
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ __('Expira:') }} {{ \Carbon\Carbon::parse($webserviceConfig['argentina']['certificate_expires_at'])->format('d/m/Y') }}
                                                </p>
                                            @endif
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ __('Sin certificado') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Webservices disponibles -->
                            @if(isset($availableWebservices['argentina']))
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Webservices Disponibles') }}</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach($availableWebservices['argentina'] as $key => $name)
                                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900">{{ $name }}</h4>
                                                    <p class="text-xs text-gray-500">{{ __('Webservice:') }} {{ $key }}</p>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" 
                                                           name="ws_config[argentina][webservices][{{ $key }}]"
                                                           id="arg_{{ $key }}"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                                           {{ in_array($key, $webserviceConfig['argentina']['webservices'] ?? []) ? 'checked' : '' }}>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Paraguay - DNA -->
                @if($webserviceConfig['paraguay']['enabled'])
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="px-6 py-4 bg-green-50 border-b border-green-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🇵🇾 {{ __('Paraguay - DNA') }}
                                </h3>
                                <div class="flex space-x-2">
                                    @foreach($availableWebservices['paraguay'] ?? [] as $key => $name)
                                        <button type="button" 
                                                onclick="testWebservice('{{ $key }}', 'PY')"
                                                class="bg-green-500 hover:bg-green-700 text-white text-xs px-3 py-1 rounded">
                                            {{ __('Test') }} {{ $key }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('RUC') }}</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ $webserviceConfig['paraguay']['ruc'] }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">{{ __('Estado') }}</label>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ __('Configurado') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Webservices disponibles -->
                            @if(isset($availableWebservices['paraguay']))
                                <div class="mt-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-3">{{ __('Webservices Disponibles') }}</label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach($availableWebservices['paraguay'] as $key => $name)
                                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                                <div>
                                                    <h4 class="text-sm font-medium text-gray-900">{{ $name }}</h4>
                                                    <p class="text-xs text-gray-500">{{ __('Webservice:') }} {{ $key }}</p>
                                                </div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" 
                                                           name="ws_config[paraguay][webservices][{{ $key }}]"
                                                           id="py_{{ $key }}"
                                                           class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded"
                                                           {{ in_array($key, $webserviceConfig['paraguay']['webservices'] ?? []) ? 'checked' : '' }}>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Botones de acción -->
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.companies.show', $company) }}" 
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        {{ __('Cancelar') }}
                    </a>
                    <button type="submit" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        {{ __('Guardar Configuración') }}
                    </button>
                </div>
            </form>

        </div>
    </div>

    @push('scripts')
    <script>
        function testWebservice(webserviceType, country) {
            const button = event.target;
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = 'Probando...';
            
            fetch(`{{ route('admin.companies.test-webservice', $company) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    webservice_type: webserviceType,
                    country: country
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ ${data.message}`);
                } else {
                    alert(`❌ ${data.message}`);
                }
            })
            .catch(error => {
                alert('❌ Error en la prueba de conexión');
                console.error('Error:', error);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = originalText;
            });
        }
    </script>
    @endpush
</x-app-layout>