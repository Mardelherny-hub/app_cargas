<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>MIC / DTA - {{ $voyage['voyage_number'] }}</title>
<style>
    @page { size: A4 portrait; margin: 5mm 6mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        color: #111;
        font-size: 6.3pt;
        line-height: 1.08;
    }
    .page { width: 100%; page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0; }
    td, th { border: 0.55pt solid #111; vertical-align: top; padding: .75mm 1mm; }
    .top-title td { padding-top: .8mm; padding-bottom: .8mm; }
    .mic-title { font-size: 17pt; font-weight: bold; white-space: nowrap; }
    .form-title { font-size: 7.2pt; font-weight: bold; line-height: 1.08; }
    .page-no { text-align: right; vertical-align: bottom; font-size: 6.3pt; }
    .label { display: block; font-size: 5pt; font-weight: normal; margin-bottom: .45mm; }
    .value {
        font-family: "DejaVu Sans Mono", "Courier New", monospace;
        font-size: 6.3pt;
        line-height: 1.12;
        white-space: pre-line;
    }
    .center { text-align: center; }
    .right { text-align: right; }
    .small { font-size: 5.7pt; }
    .container-row { margin-top: .12mm; }
    .cargo-continuation td { padding-top: .45mm; padding-bottom: .45mm; }
    .cargo-continuation .value { font-size: 5.8pt; line-height: 1.05; }
</style>
</head>
<body>
@php
    $partyText = function(array $party): string {
        $lines = array_filter([
            $party['company_name'] ?? null,
            $party['address'] ?? null,
            !empty($party['tax_id']) ? 'CUIT/RUC: '.$party['tax_id'] : null,
            !empty($party['phone']) ? 'TEL: '.$party['phone'] : null,
            !empty($party['email']) ? $party['email'] : null,
        ]);
        return implode("\n", $lines);
    };


    $normalizeTransportUnit = function (?string $value): string {
        return mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim((string) $value)));
    };

    $vesselName = trim((string) ($voyage['vessel_name'] ?? ''));
    $vesselRegistration = trim((string) ($voyage['vessel_registration'] ?? ''));
    $showVesselRegistration = $vesselRegistration !== ''
        && $normalizeTransportUnit($vesselRegistration) !== $normalizeTransportUnit($vesselName);
@endphp

@forelse($client_template_bills as $bill)
<div class="page">
    <table class="top-title">
        <tr>
            <td style="width:18%; vertical-align:middle"><div class="mic-title">MIC / DTA</div></td>
            <td style="width:68%; vertical-align:middle">
                <div class="form-title">
                    MANIFIESTO INTERNACIONAL DE CARGA FLUVIAL / DECLARAÇÃO DE TRANSITO ADUANEIRO<br>
                    MANIFIESTO INTERNACIONAL DE CARGA FLUVIAL / DECLARACIÓN DE TRÁNSITO ADUANERO
                </div>
            </td>
            <td class="page-no" style="width:14%">Page: &nbsp; {{ $loop->iteration }}</td>
        </tr>
    </table>

    <table class="r1">
        <tr>
            <td style="width:48%">
                <span class="label">1. Nombre y domicilio de la transportadora</span>
                <div class="value">{{ $company['legal_name'] }}
{{ $company['address'] }}
{{ $company['city'] }}
@if(!empty($company['tax_id']))CUIT/RUC: {{ $company['tax_id'] }}@endif</div>
            </td>
            <td style="width:13%" class="center">
                <span class="label">2. Tránsito Aduanero</span>
                <div class="value">{{ $bill['is_transit'] ? 'SI' : 'NO' }}</div>
            </td>
            <td style="width:22%">
                <span class="label">3. Nro. MIC</span>
                <div class="value">{{ ($bill['mic_dta_number'] ?? '') ?: $micdta['number'] }}</div>
            </td>
            <td style="width:17%">
                <span class="label">4. Fecha</span>
                <div class="value">{{ $micdta['date'] ?: $bill['bill_date'] }}</div>
            </td>
        </tr>
    </table>

    <table class="r2">
        <tr>
            <td style="width:48%">
                <span class="label">4. Identificación de las unidades de transporte</span>
                <div class="value">{{ $vesselName }}
@if($showVesselRegistration)
{{ $vesselRegistration }}
@endif
                </div>
            </td>
            <td style="width:52%">
                <span class="label">5. Nombre y domicilio del remitente</span>
                <div class="value">{{ $partyText($bill['shipper']) }}</div>
            </td>
        </tr>
    </table>

    <table class="r3">
        <tr>
            <td style="width:48%">
                <span class="label">7. Lugar y país de embarque</span>
                <div class="value">{{ $bill['loading_port'] }}</div>
            </td>
            <td style="width:52%">
                <span class="label">6. Nombre y país del destinatario</span>
                <div class="value">{{ $partyText($bill['consignee']) }}</div>
            </td>
        </tr>
    </table>

    <table class="r4">
        <tr>
            <td style="width:48%">
                <span class="label">8. Lugar y país de destino</span>
                <div class="value">{{ $bill['final_destination_port'] }}</div>
            </td>
            <td style="width:52%">
                <span class="label">9. Nombre y país del consignatario</span>
                <div class="value">{{ ($bill['notify']['company_name'] ?? 'No aplica') !== 'No aplica' ? $partyText($bill['notify']) : $partyText($bill['consignee']) }}</div>
            </td>
        </tr>
    </table>

    @php
        $containerChunks = collect($bill['containers'])->chunk(10);
        $firstContainerChunk = $containerChunks->shift() ?? collect();
    @endphp
    <table class="cargo">
        <tr>
            <td style="width:14%">
                <span class="label">10. Conocimiento</span>
                <div class="value">{{ $bill['bill_number'] }}</div>
            </td>
            <td style="width:34%">
                <span class="label">11. Cantidad, volumen y bultos</span>
                <div class="value">{{ $bill['total_packages'] }} BULTOS
CONTENEDORES: {{ $bill['container_count'] }}
VOLUMEN: {{ number_format((float)$bill['volume_m3'], 3, '.', '') }} M3
{{ $bill['cargo_description'] }}</div>
            </td>
            <td style="width:16%" class="right">
                <span class="label">12. Peso bruto Kg.</span>
                <div class="value">{{ number_format((float)$bill['gross_weight_kg'], 3, '.', '') }}</div>
            </td>
            <td style="width:9%" class="right">
                <span class="label">13. Valor FOB u$d</span>
                <div class="value">@if((float)$bill['declared_value'] > 0){{ number_format((float)$bill['declared_value'], 2, '.', '') }}@endif</div>
            </td>
            <td style="width:27%">
                <span class="label">14. Marcas &amp; números, descripción de la mercadería</span>
                <div class="value">@if(!empty($bill['cargo_marks'])){{ $bill['cargo_marks'] }}
@endif
@foreach($firstContainerChunk as $container)
<div class="container-row">{{ $container['number'] }}@if(!empty($container['type'])) &nbsp; {{ $container['type'] }}@endif @if(!empty($container['seals'])) &nbsp; PRECINTO: {{ $container['seals'] }}@endif</div>
@endforeach
                </div>
            </td>
        </tr>
        @foreach($containerChunks as $containerChunk)
        <tr class="cargo-continuation">
            <td style="width:14%"></td>
            <td style="width:34%"></td>
            <td style="width:16%"></td>
            <td style="width:9%"></td>
            <td style="width:27%">
                <div class="value">
@foreach($containerChunk as $container)
<div class="container-row">{{ $container['number'] }}@if(!empty($container['type'])) &nbsp; {{ $container['type'] }}@endif @if(!empty($container['seals'])) &nbsp; PRECINTO: {{ $container['seals'] }}@endif</div>
@endforeach
                </div>
            </td>
        </tr>
        @endforeach
    </table>

    <table class="r6">
        <tr>
            <td style="width:48%">
                <span class="label">15. Números de los precintos</span>
                <div class="value"></div>
            </td>
            <td style="width:52%">
                <span class="label">16. Observaciones de la Aduana de partida</span>
                <div class="value">{{ $bill['customs_remarks'] ?: $micdta['customs_observation'] }}</div>
            </td>
        </tr>
    </table>

    <table class="declaration">
        <tr>
            <td style="width:48%">
                El suscrito declara que las informaciones que figuran en este documento son exactas y auténticas y se obliga a cumplir con las disposiciones del acuerdo.
            </td>
            <td style="width:52%"></td>
        </tr>
    </table>

    <table class="sign">
        <tr>
            <td style="width:48%"><span class="label">17. Sello y firma del transportista</span></td>
            <td style="width:52%"><span class="label">18. Sello y firma de la Aduana de partida</span></td>
        </tr>
    </table>

    <table class="transport">
        <tr>
            <td style="width:48%"><span class="label">19. Transportador responsable del 1er tramo</span></td>
            <td style="width:52%"><span class="label">20. Transportador responsable del 2do tramo</span></td>
        </tr>
        <tr>
            <td><span class="label">21. Transportista responsable del 3er tramo</span></td>
            <td><span class="label">22. Transportista responsable del 4to tramo</span></td>
        </tr>
    </table>
</div>
@empty
<div style="padding:20mm;text-align:center">No hay conocimientos asociados al viaje seleccionado.</div>
@endforelse
</body>
</html>