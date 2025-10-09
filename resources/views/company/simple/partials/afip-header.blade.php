@php
    use Illuminate\Support\Facades\Route;

    // Clave activa recibida desde la vista: 'anticipada' | 'micdta' | 'desconsolidado' | 'trasbordo'
    $activeKey = $active ?? null;

    // Links de pestañas (Anticipada y Mic-DTA usan rutas reales si existen; los otros van con '#')
    $tabs = [
        [
            'key'   => 'anticipada',
            'label' => 'Anticipada',
            'href'  => Route::has('company.simple.anticipada.show')
                        ? route('company.simple.anticipada.show', $voyage)
                        : '#',
        ],
        [
            'key'   => 'micdta',
            'label' => 'Mic-DTA',
            'href'  => Route::has('company.simple.micdta.show')
                        ? route('company.simple.micdta.show', $voyage)
                        : url()->current(),
        ],
        [
            'key'   => 'desconsolidado',
            'label' => 'Desconsolidado',
            'href'  => Route::has('company.simple.desconsolidado.show')
                        ? route('company.simple.desconsolidado.show', $voyage)
                        : '#',
        ],
        [
            'key'   => 'trasbordo',
            'label' => 'Trasbordo',
            'href'  => '#', // pendiente de desarrollo
        ],
    ];
@endphp

<div class="flex items-center justify-between">
    <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Panel de Métodos AFIP - {{ $voyage->voyage_number }}
        </h2>
        <p class="text-sm text-gray-600 mt-1">
            Ejecutar métodos específicos del webservice AFIP Argentina
        </p>

        {{-- Pestañas de navegación entre módulos --}}
        <nav class="mt-4 border-b border-gray-200">
            <ul class="flex gap-4">
                @foreach($tabs as $tab)
                    @php
                        $isActive = $activeKey === $tab['key'];
                        $isDisabled = $tab['href'] === '#';
                    @endphp
                    <li>
                        <a href="{{ $tab['href'] }}"
                           @class([
                               'inline-flex items-center px-3 py-2 text-sm font-medium border-b-2 transition',
                               $isActive
                                   ? 'border-blue-600 text-blue-700'
                                   : 'border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300',
                               $isDisabled ? 'opacity-50 cursor-not-allowed' : '',
                           ])
                           @if($isDisabled) aria-disabled="true" @endif
                           @if($isActive) aria-current="page" @endif
                        >
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>

    <div class="flex items-center space-x-3">
        {{-- Botón de volver genérico (sin depender de nombres de ruta) --}}
        <button type="button"
                onclick="window.history.back()"
                class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            Volver al Viaje
        </button>

        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            {{ $company->legal_name ?? '—' }}
        </span>
    </div>
</div>
