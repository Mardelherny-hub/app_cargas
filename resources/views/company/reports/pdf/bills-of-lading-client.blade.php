<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conocimientos de Embarque</title>
    <style>
        @page { size: 8.5in 13in; margin: 12mm 9mm 14mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 7pt;
            line-height: 1.18;
        }
        .header {
            border-bottom: 1.2pt solid #111;
            padding-bottom: 5mm;
            margin-bottom: 4mm;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .company { width: 28%; font-size: 7pt; }
        .title { width: 44%; text-align: center; }
        .title h1 { margin: 0; font-size: 14pt; letter-spacing: .3pt; }
        .title div { margin-top: 1mm; font-size: 7pt; }
        .meta { width: 28%; text-align: right; font-size: 6.5pt; }
        .filters {
            margin-bottom: 4mm;
            padding: 2.5mm 3mm;
            border: .6pt solid #777;
            background: #f5f5f5;
            font-size: 6.5pt;
        }
        .bills {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .bills thead { display: table-header-group; }
        .bills tr { page-break-inside: avoid; }
        .bills th,
        .bills td {
            border: .55pt solid #222;
            padding: 1.6mm 1.4mm;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-wrap: break-word;
        }
        .bills th {
            background: #ececec;
            font-size: 6pt;
            text-align: left;
        }
        .num { width: 3%; text-align: center; }
        .bl { width: 12%; font-weight: bold; }
        .date { width: 7%; }
        .voyage { width: 9%; }
        .party { width: 16%; }
        .ports { width: 19%; }
        .qty { width: 6%; text-align: right; }
        .weight { width: 8%; text-align: right; }
        .small { font-size: 5.8pt; }
        .muted { color: #555; }
        .totals {
            margin-top: 4mm;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .totals td {
            border: .55pt solid #222;
            padding: 2mm;
            font-size: 7pt;
        }
        .totals .label { font-weight: bold; background: #f3f3f3; }
        .right { text-align: right; }
        .footer {
            margin-top: 4mm;
            padding-top: 2mm;
            border-top: .55pt solid #777;
            font-size: 5.8pt;
            color: #555;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="company">
                    <strong>{{ $company['legal_name'] }}</strong>
                    @if(!empty($company['tax_id']))
                        <br>CUIT/RUC: {{ $company['tax_id'] }}
                    @endif
                </td>
                <td class="title">
                    <h1>CONOCIMIENTOS DE EMBARQUE</h1>
                    <div>Formato solicitado · Hoja Oficio</div>
                </td>
                <td class="meta">
                    Generado: {{ $metadata['generated_at'] }}<br>
                    Registros: {{ $metadata['record_count'] }}
                </td>
            </tr>
        </table>
    </div>

    @if(!empty($filters))
        <div class="filters">
            <strong>Filtros aplicados:</strong> {{ implode(' | ', $filters) }}
        </div>
    @endif

    <table class="bills">
        <thead>
            <tr>
                <th class="num">#</th>
                <th class="bl">Conocimiento</th>
                <th class="date">Fecha</th>
                <th class="voyage">Viaje</th>
                <th class="party">Cargador</th>
                <th class="party">Consignatario</th>
                <th class="ports">Puertos</th>
                <th class="qty">Bultos</th>
                <th class="weight">Peso bruto kg</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills_of_lading as $bill)
                <tr>
                    <td class="num">{{ $bill['line_number'] }}</td>
                    <td class="bl">{{ $bill['bill_number'] }}</td>
                    <td class="date">{{ $bill['bill_date'] }}</td>
                    <td class="voyage">
                        {{ $bill['voyage_number'] }}
                        @if(!empty($bill['vessel_name']) && $bill['vessel_name'] !== 'N/A')
                            <br><span class="small muted">{{ $bill['vessel_name'] }}</span>
                        @endif
                    </td>
                    <td class="party">
                        {{ $bill['shipper_name'] }}
                        @if(!empty($bill['shipper_tax_id']))
                            <br><span class="small muted">{{ $bill['shipper_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="party">
                        {{ $bill['consignee_name'] }}
                        @if(!empty($bill['consignee_tax_id']))
                            <br><span class="small muted">{{ $bill['consignee_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="ports">
                        <strong>Carga:</strong> {{ $bill['loading_port'] }}<br>
                        <strong>Descarga:</strong> {{ $bill['discharge_port'] }}<br>
                        <strong>Destino final:</strong> {{ $bill['final_destination_port'] }}
                    </td>
                    <td class="qty">{{ number_format((float) $bill['total_packages'], 0, ',', '.') }}</td>
                    <td class="weight">{{ number_format((float) $bill['gross_weight_kg'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:8mm;">
                        No hay conocimientos que coincidan con la selección realizada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total conocimientos</td>
            <td class="right">{{ number_format((float) $totals['total_bills'], 0, ',', '.') }}</td>
            <td class="label">Total bultos</td>
            <td class="right">{{ number_format((float) $totals['total_packages'], 0, ',', '.') }}</td>
            <td class="label">Peso bruto total kg</td>
            <td class="right">{{ number_format((float) $totals['total_gross_weight_kg'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Generado por {{ $metadata['generated_by'] }} · {{ $metadata['generated_by_company'] }}
    </div>
</body>
</html>
