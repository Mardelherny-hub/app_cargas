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
    .cargo td, .cargo th { padding-top: .8mm; padding-bottom: .8mm; }
    .cargo-row { page-break-inside: avoid; }
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

    // Normaliza saltos y fragmenta cada línea sin descartar caracteres visibles.
    $wrapText = function (?string $text, int $width) {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        $chunks = [];
        foreach (explode("\n", $text) as $line) {
            preg_match_all('/[\s\S]{1,'.$width.'}/u', $line, $matches);
            foreach ($matches[0] ?? [] as $chunk) {
                if (preg_match('/[^\s\p{Z}]/u', $chunk)) {
                    $chunks[] = $chunk;
                }
            }
        }
        return collect($chunks);
    };

    $estimateLines = function (?string $text, int $width): int {
        $lines = 0;
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        foreach (explode("\n", $text) as $line) {
            $lines += max(1, (int) ceil(mb_strlen($line) / $width));
        }
        return max(1, $lines);
    };

    $buildCargoRows = function (array $item, array $containers = [], bool $legacy = false) use ($wrapText, $estimateLines) {
        $containers = array_values($containers);
        $containerCount = count($containers);
        $singleContainer = !$legacy && $containerCount === 1;
        $baseRow = [
            'bill_number' => $item['_bill_number'] ?? '',
            'first_part' => true,
            'label' => $legacy ? 'Total del conocimiento' : 'Total del ítem',
            'container_count' => $containerCount,
            'quantity' => $item['quantity'] ?? null,
            'package_type' => $item['package_type'] ?? null,
            'volume_m3' => $item['volume_m3'] ?? null,
            'description' => $item['description'] ?? '',
            'commodity_code' => $item['commodity_code'] ?? null,
            'net_weight_kg' => $item['net_weight_kg'] ?? null,
            'gross_weight_kg' => $item['gross_weight_kg'] ?? null,
            'declared_value' => $item['declared_value'] ?? null,
            'cargo_marks' => $item['cargo_marks'] ?? '',
            'container_text' => '',
            'container_number' => null,
        ];
        $detailRows = $singleContainer ? [] : [$baseRow];

        foreach ($containers as $container) {
            $row = $baseRow;
            $row['label'] = $legacy ? 'Contenedor del conocimiento' : 'Contenedor del ítem';
            $row['container_count'] = $singleContainer ? 1 : 0;
            $row['container_number'] = (string) ($container['number'] ?? '');
            foreach ([
                'quantity' => 'package_quantity',
                'gross_weight_kg' => 'gross_weight_kg',
                'net_weight_kg' => 'net_weight_kg',
                'volume_m3' => 'volume_m3',
            ] as $field => $pivotField) {
                $row[$field] = $container[$pivotField] ?? ($singleContainer ? $baseRow[$field] : null);
            }
            if (!$singleContainer) {
                $row['description'] = '';
                $row['cargo_marks'] = '';
                $row['declared_value'] = null;
            }
            $row['container_text'] = $row['container_number'];
            if (!empty($container['type'])) {
                $row['container_text'] .= ' ['.$container['type'].']';
            }
            if (!empty($container['seals'])) {
                $row['container_text'] .= "\n".(string) $container['seals'];
            }
            $detailRows[] = $row;
        }

        $rows = collect();
        foreach ($detailRows as $detail) {
            $descriptionChunks = $wrapText($detail['description'], 52)->all();
            $marksChunks = $wrapText($detail['cargo_marks'], 40)->all();
            $partCount = max(1, count($descriptionChunks), count($marksChunks));
            for ($part = 0; $part < $partCount; $part++) {
                $row = $detail;
                $row['description'] = $descriptionChunks[$part] ?? '';
                $row['cargo_marks'] = $marksChunks[$part] ?? '';
                if ($part > 0) {
                    $row['first_part'] = false;
                    $row['label'] = $legacy ? 'Continuación del conocimiento' : 'Continuación del ítem';
                    foreach (['quantity', 'volume_m3', 'net_weight_kg', 'gross_weight_kg', 'declared_value'] as $field) {
                        $row[$field] = null;
                    }
                    $row['container_text'] = '';
                    $row['container_number'] = null;
                }

                $column11 = implode("\n", array_filter([
                    $row['label'],
                    $row['first_part'] && $row['container_count'] > 0
                        ? $row['container_count'].' '.($row['container_count'] === 1 ? 'CONTENEDOR' : 'CONTENEDORES')
                        : null,
                    $row['quantity'] !== null ? $row['quantity'].' BULTOS' : null,
                    $row['volume_m3'] !== null ? 'VOLUMEN: '.$row['volume_m3'].' M3' : null,
                    $row['description'],
                    !empty($row['commodity_code']) ? 'HS/NCM: '.$row['commodity_code'] : null,
                    $row['net_weight_kg'] !== null ? 'NET WEIGHT: '.number_format((float) $row['net_weight_kg'], 3, '.', '').' KGS' : null,
                ], static fn ($value) => $value !== null && $value !== ''));

                $column14 = implode("\n", array_filter([
                    $row['cargo_marks'],
                    $row['container_text'],
                ], static fn ($value) => $value !== ''));

                $lineCount = max(
                    1,
                    $estimateLines((string) $row['bill_number'], 20),
                    $estimateLines($column11, 52),
                    $row['gross_weight_kg'] === null
                        ? 1
                        : $estimateLines(number_format((float) $row['gross_weight_kg'], 3, '.', ''), 18),
                    $row['declared_value'] === null
                        ? 1
                        : $estimateLines(number_format((float) $row['declared_value'], 2, '.', ''), 10),
                    $estimateLines($column14, 40)
                );
                $row['_line_cost'] = $lineCount + 1; // Padding vertical y bordes por fila.
                $rows->push($row);
            }
        }

        return $rows;
    };

    $documentPages = collect();
    $pageLineBudget = 18;

    foreach ($client_template_bills as $bill) {
        $items = collect($bill['items'] ?? []);
        $cargoRows = collect();

        if ($items->isNotEmpty()) {
            foreach ($items as $item) {
                $item['_bill_number'] = $bill['bill_number'] ?? '';
                $cargoRows = $cargoRows->concat(
                    $buildCargoRows($item, array_values($item['containers'] ?? []))
                );
            }
        } else {
            // Compatibilidad con el formato histórico sin items detallados.
            $legacyItem = [
                '_bill_number' => $bill['bill_number'] ?? '',
                'quantity' => $bill['total_packages'] ?? null,
                'package_type' => 'BULTOS',
                'description' => $bill['cargo_description'] ?? '',
                'commodity_code' => null,
                'net_weight_kg' => null,
                'gross_weight_kg' => $bill['gross_weight_kg'] ?? null,
                'volume_m3' => $bill['volume_m3'] ?? null,
                'declared_value' => $bill['declared_value'] ?? null,
                'cargo_marks' => $bill['cargo_marks'] ?? '',
            ];
            $cargoRows = $buildCargoRows(
                $legacyItem,
                array_values($bill['containers'] ?? []),
                true
            );
        }

        $pageRows = collect();
        $usedLines = 0;
        foreach ($cargoRows as $row) {
            $lineCost = $row['_line_cost'];
            unset($row['_line_cost']);

            if ($lineCost > $pageLineBudget) {
                $detailName = $row['container_number'] !== null
                    ? 'del contenedor '.$row['container_number']
                    : 'del ítem';
                throw new \RuntimeException(
                    'El detalle '.$detailName.' del conocimiento '.$row['bill_number'].
                    ' es demasiado extenso para imprimirse en una sola página MIC/DTA.'
                );
            }

            if ($pageRows->isNotEmpty() && $usedLines + $lineCost > $pageLineBudget) {
                $documentPages->push([
                    'bill' => $bill,
                    'rows' => $pageRows->values(),
                ]);
                $pageRows = collect();
                $usedLines = 0;
            }

            $pageRows->push($row);
            $usedLines += $lineCost;
        }

        if ($pageRows->isNotEmpty()) {
            $documentPages->push([
                'bill' => $bill,
                'rows' => $pageRows->values(),
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
