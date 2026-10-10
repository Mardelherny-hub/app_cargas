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
        height: 286mm;
        position: relative;
    }
    .page + .page { page-break-before: always; }
    .page-content { padding-bottom: 0; }
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
    .cargo td, .cargo th { padding-top: .8mm; padding-bottom: .8mm; }
    .cargo-row { page-break-inside: avoid; }
    .cargo-row td { height: 128mm; }
    .cargo-heading { font-weight: normal; text-align: left; }
    .container-line { white-space: pre-line; overflow-wrap: anywhere; word-wrap: break-word; }
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

    /*
     * El modelo entregado trabaja por conocimiento, no por ítem/contenedor:
     * campos 10-14 forman una única fila lógica del B/L. Cuando el contenido
     * no entra, continúa en otra hoja sin repetir pesos/cantidades.
     */
    $wrapText = function (?string $text, int $width): array {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string) $text));
        if ($text === '') {
            return [];
        }

        $lines = [];
        foreach (explode("\n", $text) as $sourceLine) {
            $sourceLine = trim($sourceLine);
            if ($sourceLine === '') {
                continue;
            }

            while (mb_strlen($sourceLine) > $width) {
                $piece = mb_substr($sourceLine, 0, $width);
                $breakAt = mb_strrpos($piece, ' ');
                if ($breakAt !== false && $breakAt > (int) floor($width * .55)) {
                    $piece = mb_substr($sourceLine, 0, $breakAt);
                    $sourceLine = ltrim(mb_substr($sourceLine, $breakAt + 1));
                } else {
                    $sourceLine = mb_substr($sourceLine, $width);
                }
                $lines[] = $piece;
            }

            if ($sourceLine !== '') {
                $lines[] = $sourceLine;
            }
        }

        return $lines;
    };

    $documentPages = collect();
    $pageLineBudget = 18;

    foreach ($client_template_bills as $bill) {
        $items = collect($bill['items'] ?? []);

        $containers = $items->isNotEmpty()
            ? $items
                ->flatMap(fn ($item) => $item['containers'] ?? [])
                ->unique('number')
                ->values()
            : collect($bill['containers'] ?? [])
                ->unique('number')
                ->values();

        $descriptionParts = $items
            ->map(fn ($item) => trim((string) ($item['description'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        if ($descriptionParts->isEmpty() && !empty($bill['cargo_description'])) {
            $descriptionParts->push(trim((string) $bill['cargo_description']));
        }

        $commodityCodes = $items
            ->pluck('commodity_code')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        $netWeight = $items
            ->pluck('net_weight_kg')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->sum();

        $goodsLines = [];
        foreach ($descriptionParts as $description) {
            $goodsLines = array_merge($goodsLines, $wrapText($description, 52));
        }
        if ($commodityCodes->isNotEmpty()) {
            $goodsLines = array_merge(
                $goodsLines,
                $wrapText('HS/NCM: '.$commodityCodes->implode(', '), 52)
            );
        }
        if ((float) $netWeight > 0) {
            $goodsLines[] = 'NET WEIGHT: '
                . number_format((float) $netWeight, 3, '.', '')
                . ' KGS';
        }

        $markParts = collect();
        if (!empty($bill['cargo_marks'])) {
            $markParts->push(trim((string) $bill['cargo_marks']));
        }
        $items->pluck('cargo_marks')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->each(fn ($value) => $markParts->push($value));

        foreach ($containers as $container) {
            $text = trim((string) ($container['number'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (!empty($container['type'])) {
                $text .= ' ['.(string) $container['type'].']';
            }
            if (!empty($container['seals'])) {
                $text .= "\n".(string) $container['seals'];
            }
            $markParts->push($text);
        }

        $marksLines = $wrapText($markParts->unique()->implode("\n"), 40);

        $lineCount = max(1, count($goodsLines), count($marksLines));
        $pageCount = max(1, (int) ceil($lineCount / $pageLineBudget));

        for ($page = 0; $page < $pageCount; $page++) {
            $offset = $page * $pageLineBudget;
            $firstPart = $page === 0;

            $row = [
                'bill_number' => $bill['bill_number'] ?? '',
                'first_part' => $firstPart,
                'label' => $firstPart ? '' : 'Continuación',
                'container_count' => $firstPart ? $containers->count() : 0,
                'quantity' => $firstPart ? ($bill['total_packages'] ?? null) : null,
                'volume_m3' => $firstPart ? ($bill['volume_m3'] ?? null) : null,
                'description' => implode("\n", array_slice(
                    $goodsLines,
                    $offset,
                    $pageLineBudget
                )),
                'commodity_code' => null,
                'net_weight_kg' => null,
                'gross_weight_kg' => $firstPart
                    ? ($bill['gross_weight_kg'] ?? null)
                    : null,
                'declared_value' => $firstPart
                    ? ($bill['declared_value'] ?? null)
                    : null,
                'cargo_marks' => implode("\n", array_slice(
                    $marksLines,
                    $offset,
                    $pageLineBudget
                )),
                'container_text' => '',
                'container_number' => null,
            ];

            $documentPages->push([
                'bill' => $bill,
                'rows' => collect([$row]),
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
            <thead>
                <tr>
                    <th class="cargo-heading" style="width:14%">10. Conocimiento</th>
                    <th class="cargo-heading" style="width:34%">11. Cantidad, volumen y bultos</th>
                    <th class="cargo-heading right" style="width:16%">12. Peso bruto Kg.</th>
                    <th class="cargo-heading right" style="width:9%">13. Valor FOB u$d</th>
                    <th class="cargo-heading" style="width:27%">14. Marcas &amp; números, descripción de la mercadería</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr class="cargo-row">
                    <td><div class="value">{{ $row['bill_number'] }}</div></td>
                    <td>
                        <div class="value">{{ $row['label'] }}
@if($row['first_part'] && $row['container_count'] > 0){{ $row['container_count'] }} {{ $row['container_count'] === 1 ? 'CONTENEDOR' : 'CONTENEDORES' }}
@endif
@if($row['quantity'] !== null){{ $row['quantity'] }} BULTOS
@endif
@if($row['volume_m3'] !== null)VOLUMEN: {{ $row['volume_m3'] }} M3
@endif
@if($row['description'] !== ''){{ $row['description'] }}
@endif
@if(!empty($row['commodity_code']))HS/NCM: {{ $row['commodity_code'] }}
@endif
@if($row['net_weight_kg'] !== null)NET WEIGHT: {{ number_format((float)$row['net_weight_kg'], 3, '.', '') }} KGS
@endif</div>
                    </td>
                    <td class="right">
                        @if($row['gross_weight_kg'] !== null)
                            <div class="value">{{ number_format((float)$row['gross_weight_kg'], 3, '.', '') }}</div>
                        @endif
                    </td>
                    <td class="right">
                        @if((float)($row['declared_value'] ?? 0) > 0)
                            <div class="value">{{ number_format((float)$row['declared_value'], 2, '.', '') }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="value">@if($row['cargo_marks'] !== ''){{ $row['cargo_marks'] }}
@endif
@if($row['container_text'] !== '')<div class="container-line">{{ $row['container_text'] }}</div>@endif</div>
                    </td>
                </tr>
                @endforeach
            </tbody>
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
                    <div class="value">{{ ($bill['export_permit'] ?? '') ?: (($bill['customs_remarks'] ?? '') ?: $micdta['customs_observation']) }}</div>
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
