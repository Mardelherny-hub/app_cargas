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
    .page {
        width: 100%;
        height: 287mm;
        position: relative;
        page-break-after: always;
        overflow: hidden;
    }
    .page:last-child { page-break-after: auto; }
    .page-content { padding-bottom: 92mm; }
    .page-footer {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 90mm;
    }
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
    .cargo td { padding-top: .8mm; padding-bottom: .8mm; }
    .cargo-row { page-break-inside: avoid; }
    .container-line { margin-bottom: .8mm; }
    .item-line { margin-bottom: .45mm; }
    .r6 td { height: 22mm; }
    .declaration td { height: 14mm; vertical-align: middle; }
    .sign td { height: 18mm; }
    .transport td { height: 18mm; }
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

    $chunkText = function (?string $text, int $maxLength = 260) {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        if ($text === '') {
            return collect(['']);
        }

        $chunks = [];
        $current = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && mb_strlen($candidate) > $maxLength) {
                $chunks[] = $current;
                $current = $word;
                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return collect($chunks ?: ['']);
    };

    $buildCargoRows = function (array $bill) use ($chunkText) {
        $rows = collect();
        $items = collect($bill['items'] ?? []);

        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                $containers = collect($item['containers'] ?? [])->values();
                $descriptionChunks = $chunkText($item['description'] ?? '', 260);
                $containerChunks = $containers->chunk(3)->values();
                $parts = max(1, $descriptionChunks->count(), $containerChunks->count());

                for ($part = 0; $part < $parts; $part++) {
                    $rows->push([
                        'first_part' => $part === 0,
                        'container_count' => $containers->count(),
                        'quantity' => $part === 0 ? ($item['quantity'] ?? null) : null,
                        'package_type' => $part === 0 ? ($item['package_type'] ?? null) : null,
                        'description' => $descriptionChunks->get($part, ''),
                        'commodity_code' => $part === 0 ? ($item['commodity_code'] ?? null) : null,
                        'net_weight_kg' => $part === 0 ? ($item['net_weight_kg'] ?? null) : null,
                        'gross_weight_kg' => $part === 0 ? ($item['gross_weight_kg'] ?? null) : null,
                        'declared_value' => $part === 0 ? ($item['declared_value'] ?? null) : null,
                        'cargo_marks' => $part === 0 ? ($item['cargo_marks'] ?? null) : null,
                        'containers' => $containerChunks->get($part, collect()),
                    ]);
                }
            }

            return $rows;
        }

        // Compatibilidad con reportes antiguos o datos sin items detallados.
        $descriptionChunks = $chunkText($bill['cargo_description'] ?? '', 650);
        $containerChunks = collect($bill['containers'] ?? [])->chunk(8)->values();
        $parts = max(1, $descriptionChunks->count(), $containerChunks->count());

        for ($part = 0; $part < $parts; $part++) {
            $rows->push([
                'first_part' => $part === 0,
                'container_count' => $bill['container_count'] ?? 0,
                'quantity' => $part === 0 ? ($bill['total_packages'] ?? null) : null,
                'package_type' => $part === 0 ? 'BULTOS' : null,
                'description' => $descriptionChunks->get($part, ''),
                'commodity_code' => null,
                'net_weight_kg' => null,
                'gross_weight_kg' => $part === 0 ? ($bill['gross_weight_kg'] ?? null) : null,
                'declared_value' => $part === 0 ? ($bill['declared_value'] ?? null) : null,
                'cargo_marks' => $part === 0 ? ($bill['cargo_marks'] ?? null) : null,
                'containers' => $containerChunks->get($part, collect()),
            ]);
        }

        return $rows;
    };

    $documentPages = collect();

    foreach ($client_template_bills as $bill) {
        $cargoRows = $buildCargoRows($bill);
        $cargoPages = $cargoRows->chunk(4)->values();

        if ($cargoPages->isEmpty()) {
            $cargoPages = collect([collect()]);
        }

        foreach ($cargoPages as $rows) {
            $documentPages->push([
                'bill' => $bill,
                'rows' => $rows,
            ]);
        }
    }

    $vesselName = trim((string) ($voyage['vessel_name'] ?? ''));
    $vesselRegistration = trim((string) ($voyage['vessel_registration'] ?? ''));
    $showVesselRegistration = $vesselRegistration !== ''
        && $normalizeTransportUnit($vesselRegistration) !== $normalizeTransportUnit($vesselName);
@endphp

@forelse($documentPages as $pageIndex => $pageData)
@php
    $bill = $pageData['bill'];
    $rows = $pageData['rows'];
@endphp
<div class="page">
    <div class="page-content">
        <table class="top-title">
            <tr>
                <td style="width:18%; vertical-align:middle"><div class="mic-title">MIC / DTA</div></td>
                <td style="width:68%; vertical-align:middle">
                    <div class="form-title">
                        MANIFIESTO INTERNACIONAL DE CARGA FLUVIAL / DECLARAÇÃO DE TRANSITO ADUANEIRO<br>
                        MANIFIESTO INTERNACIONAL DE CARGA FLUVIAL / DECLARACIÓN DE TRÁNSITO ADUANERO
                    </div>
                </td>
                <td class="page-no" style="width:14%">Page: &nbsp; {{ $pageIndex + 1 }}</td>
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

        <table class="cargo">
            @foreach($rows as $rowIndex => $row)
            <tr class="cargo-row">
                <td style="width:14%">
                    @if($rowIndex === 0)
                        <span class="label">10. Conocimiento</span>
                        <div class="value">{{ $bill['bill_number'] }}</div>
                    @endif
                </td>
                <td style="width:34%">
                    @if($rowIndex === 0)
                        <span class="label">11. Cantidad, volumen y bultos</span>
                    @endif
                    <div class="value">@if($row['first_part'] && $row['container_count'] > 0){{ $row['container_count'] }} {{ $row['container_count'] === 1 ? 'CONTENEDOR' : 'CONTENEDORES' }}
@endif
@if($row['quantity'] !== null){{ $row['quantity'] }} BULTOS
@endif
@if($row['description'] !== ''){{ $row['description'] }}
@endif
@if(!empty($row['commodity_code']))HS/NCM: {{ $row['commodity_code'] }}
@endif
@if($row['net_weight_kg'] !== null)NET WEIGHT: {{ number_format((float)$row['net_weight_kg'], 3, '.', '') }} KGS
@endif</div>
                </td>
                <td style="width:16%" class="right">
                    @if($rowIndex === 0)<span class="label">12. Peso bruto Kg.</span>@endif
                    @if($row['gross_weight_kg'] !== null)
                        <div class="value">{{ number_format((float)$row['gross_weight_kg'], 3, '.', '') }}</div>
                    @endif
                </td>
                <td style="width:9%" class="right">
                    @if($rowIndex === 0)<span class="label">13. Valor FOB u$d</span>@endif
                    @if((float)($row['declared_value'] ?? 0) > 0)
                        <div class="value">{{ number_format((float)$row['declared_value'], 2, '.', '') }}</div>
                    @endif
                </td>
                <td style="width:27%">
                    @if($rowIndex === 0)
                        <span class="label">14. Marcas &amp; números, descripción de la mercadería</span>
                    @endif
                    <div class="value">@if(!empty($row['cargo_marks'])){{ $row['cargo_marks'] }}
@endif
@foreach($row['containers'] as $container)
<div class="container-line">{{ $container['number'] }}@if(!empty($container['type'])) &nbsp; {{ $container['type'] }}@endif
@if(!empty($container['seals'])){{ $container['seals'] }}@endif</div>
@endforeach
                    </div>
                </td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="page-footer">
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
</div>
@empty
<div style="padding:20mm;text-align:center">No hay conocimientos asociados al viaje seleccionado.</div>
@endforelse
</body>
</html>
