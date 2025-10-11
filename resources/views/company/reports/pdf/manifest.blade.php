<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifiesto de Carga - {{ $voyage['voyage_number'] }}</title>
    <style>
        /* === CONFIGURACIÓN DE PÁGINA === */
        @page {
            size: A4 landscape;
            margin: 15mm 10mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #000;
        }

        /* === ENCABEZADO === */
        .header {
            margin-bottom: 15px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }

        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .header-logo {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
        }

        .header-logo img {
            max-height: 50px;
            max-width: 150px;
        }

        .header-title {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: middle;
        }

        .header-title h1 {
            font-size: 18pt;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .header-title h2 {
            font-size: 14pt;
            font-weight: normal;
            color: #374151;
        }

        .header-company {
            display: table-cell;
            width: 25%;
            text-align: right;
            vertical-align: middle;
            font-size: 8pt;
        }

        .header-company strong {
            display: block;
            font-size: 10pt;
            color: #1f2937;
            margin-bottom: 2px;
        }

        /* === INFORMACIÓN DEL VIAJE === */
        .voyage-info {
            background-color: #f3f4f6;
            padding: 8px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .voyage-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .voyage-info td {
            padding: 3px 8px;
            font-size: 8pt;
        }

        .voyage-info td strong {
            color: #374151;
            font-weight: bold;
        }

        .voyage-info .section-title {
            background-color: #dbeafe;
            font-weight: bold;
            color: #1e40af;
            padding: 4px 8px;
            font-size: 9pt;
        }

        /* === TABLA DE CONOCIMIENTOS === */
        .bills-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8pt;
        }

        .bills-table thead {
            background-color: #1e40af;
            color: white;
        }

        .bills-table thead th {
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            border: 1px solid #1e3a8a;
        }

        .bills-table tbody td {
            padding: 4px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .bills-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .bills-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        /* Columnas específicas */
        .col-line { width: 3%; text-align: center; }
        .col-bl { width: 10%; font-weight: bold; }
        .col-shipper { width: 17%; }
        .col-consignee { width: 17%; }
        .col-packages { width: 6%; text-align: right; }
        .col-weight { width: 8%; text-align: right; }
        .col-volume { width: 6%; text-align: right; }
        .col-description { width: 20%; }
        .col-ports { width: 13%; font-size: 7pt; }

        /* === SECCIÓN DE TOTALES === */
        .totals-section {
            background-color: #dbeafe;
            border: 2px solid #2563eb;
            padding: 8px;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 4px 8px;
            font-size: 9pt;
        }

        .totals-table .label {
            font-weight: bold;
            color: #1e40af;
            width: 70%;
        }

        .totals-table .value {
            text-align: right;
            font-weight: bold;
            color: #1f2937;
            font-size: 10pt;
        }

        /* === PIE DE PÁGINA === */
        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #d1d5db;
            font-size: 7pt;
            color: #6b7280;
        }

        .footer table {
            width: 100%;
        }

        .footer td {
            padding: 2px 0;
        }

        /* === BADGES Y ETIQUETAS === */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        /* === UTILIDADES === */
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .text-small { font-size: 7pt; }
        .text-muted { color: #6b7280; }

        /* === MANEJO DE SALTOS DE PÁGINA === */
        .no-break {
            page-break-inside: avoid;
        }

        h1, h2, h3 {
            page-break-after: avoid;
        }

        .bills-table thead {
            display: table-header-group;
        }
    </style>
</head>
<body>
    <!-- ENCABEZADO -->
    <div class="header">
        <div class="header-top">
            <div class="header-logo">
                @if(isset($company_logo) && $company_logo)
                    <img src="{{ $company_logo }}" alt="Logo">
                @else
                    <div style="font-size: 12pt; font-weight: bold; color: #2563eb;">
                        {{ $voyage['company_commercial_name'] ?? $voyage['company_name'] }}
                    </div>
                @endif
            </div>
            
            <div class="header-title">
                <h1>MANIFIESTO DE CARGA</h1>
                <h2>{{ $voyage['voyage_number'] }}</h2>
            </div>
            
            <div class="header-company">
                <strong>{{ $voyage['company_name'] }}</strong>
                @if($voyage['company_tax_id'])
                    CUIT/RUC: {{ $voyage['company_tax_id'] }}<br>
                @endif
                Fecha: {{ $metadata['generated_at'] }}
            </div>
        </div>
    </div>

    <!-- INFORMACIÓN DEL VIAJE -->
    <div class="voyage-info no-break">
        <table>
            <tr>
                <td colspan="4" class="section-title">DATOS DEL VIAJE</td>
            </tr>
            <tr>
                <td width="25%"><strong>Número de Viaje:</strong> {{ $voyage['voyage_number'] }}</td>
                <td width="25%"><strong>Tipo:</strong> {{ ucfirst($voyage['voyage_type']) }}</td>
                <td width="25%"><strong>Estado:</strong> {{ ucfirst($voyage['status']) }}</td>
                <td width="25%"><strong>Convoy:</strong> {{ $voyage['is_convoy'] ? 'Sí' : 'No' }} ({{ $voyage['vessel_count'] }} embarcación/es)</td>
            </tr>
            <tr>
                <td colspan="4" class="section-title">EMBARCACIÓN Y TRIPULACIÓN</td>
            </tr>
            <tr>
                <td width="25%"><strong>Embarcación:</strong> {{ $voyage['vessel_name'] }}</td>
                <td width="25%">
                    @if($voyage['vessel_imo'])
                        <strong>IMO:</strong> {{ $voyage['vessel_imo'] }}
                    @endif
                </td>
                <td width="25%"><strong>Capitán:</strong> {{ $voyage['captain_name'] }}</td>
                <td width="25%">
                    @if($voyage['captain_license'])
                        <strong>Licencia:</strong> {{ $voyage['captain_license'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <td colspan="4" class="section-title">RUTA Y FECHAS</td>
            </tr>
            <tr>
                <td width="25%"><strong>Puerto Origen:</strong> {{ $voyage['origin_port'] }} ({{ $voyage['origin_port_code'] }})</td>
                <td width="25%"><strong>País Origen:</strong> {{ $voyage['origin_country'] }}</td>
                <td width="25%"><strong>Fecha Salida:</strong> {{ $voyage['departure_date'] }}</td>
                <td width="25%" rowspan="2" style="vertical-align: top;">
                    @if($voyage['transshipment_port'])
                        <strong>Transbordo:</strong> {{ $voyage['transshipment_port'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <td width="25%"><strong>Puerto Destino:</strong> {{ $voyage['destination_port'] }} ({{ $voyage['destination_port_code'] }})</td>
                <td width="25%"><strong>País Destino:</strong> {{ $voyage['destination_country'] }}</td>
                <td width="25%"><strong>Arribo Estimado:</strong> {{ $voyage['estimated_arrival_date'] }}</td>
            </tr>
        </table>
    </div>

    <!-- TABLA DE CONOCIMIENTOS DE EMBARQUE -->
    <table class="bills-table">
        <thead>
            <tr>
                <th class="col-line">#</th>
                <th class="col-bl">BL Number</th>
                <th class="col-shipper">Cargador</th>
                <th class="col-consignee">Consignatario</th>
                <th class="col-packages">Bultos</th>
                <th class="col-weight">Peso Bruto (kg)</th>
                <th class="col-volume">Vol (m³)</th>
                <th class="col-description">Descripción de Carga</th>
                <th class="col-ports">Puertos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills_of_lading as $bill)
                <tr>
                    <td class="col-line">{{ $bill['line_number'] }}</td>
                    <td class="col-bl">
                        {{ $bill['bill_number'] }}
                        @if($bill['contains_dangerous_goods'])
                            <span class="badge badge-danger">PELIGROSO</span>
                        @endif
                        @if($bill['requires_refrigeration'])
                            <span class="badge badge-info">REFRIG</span>
                        @endif
                    </td>
                    <td class="col-shipper">
                        <strong>{{ $bill['shipper_name'] }}</strong><br>
                        @if($bill['shipper_tax_id'])
                            <span class="text-small text-muted">{{ $bill['shipper_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="col-consignee">
                        <strong>{{ $bill['consignee_name'] }}</strong><br>
                        @if($bill['consignee_tax_id'])
                            <span class="text-small text-muted">{{ $bill['consignee_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="col-packages">{{ number_format($bill['total_packages'], 0, ',', '.') }}</td>
                    <td class="col-weight">{{ number_format($bill['gross_weight_kg'], 2, ',', '.') }}</td>
                    <td class="col-volume">{{ number_format($bill['volume_m3'], 2, ',', '.') }}</td>
                    <td class="col-description">
                        {{ \Illuminate\Support\Str::limit($bill['cargo_description'], 100) }}
                        @if($bill['commodity_code'])
                            <br><span class="text-small text-muted">NCM: {{ $bill['commodity_code'] }}</span>
                        @endif
                    </td>
                    <td class="col-ports text-small">
                        <strong>Carga:</strong> {{ $bill['loading_port'] }}<br>
                        <strong>Desc:</strong> {{ $bill['discharge_port'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 20px; color: #6b7280;">
                        No hay conocimientos de embarque registrados para este viaje
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- SECCIÓN DE TOTALES -->
    <div class="totals-section no-break">
        <table class="totals-table">
            <tr>
                <td class="label">TOTAL DE CONOCIMIENTOS DE EMBARQUE:</td>
                <td class="value">{{ number_format($totals['total_bills'], 0, ',', '.') }}</td>
                <td class="label">TOTAL BULTOS:</td>
                <td class="value">{{ number_format($totals['total_packages'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">PESO BRUTO TOTAL (kg):</td>
                <td class="value">{{ number_format($totals['total_gross_weight_kg'], 2, ',', '.') }}</td>
                <td class="label">PESO BRUTO TOTAL (tons):</td>
                <td class="value">{{ number_format($totals['total_gross_weight_tons'], 3, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">VOLUMEN TOTAL (m³):</td>
                <td class="value">{{ number_format($totals['total_volume_m3'], 2, ',', '.') }}</td>
                <td class="label">CARGADORES ÚNICOS:</td>
                <td class="value">{{ $totals['unique_shippers'] }}</td>
            </tr>
            @if($totals['bills_with_dangerous_goods'] > 0)
            <tr>
                <td colspan="4" style="color: #991b1b; font-weight: bold; text-align: center; padding-top: 6px;">
                    ⚠️ ATENCIÓN: Este viaje contiene {{ $totals['bills_with_dangerous_goods'] }} conocimiento(s) con mercancías peligrosas
                </td>
            </tr>
            @endif
        </table>
    </div>

    <!-- PIE DE PÁGINA -->
    <div class="footer">
        <table>
            <tr>
                <td width="33%">
                    <strong>Reporte generado por:</strong> {{ $metadata['generated_by'] }}<br>
                    <strong>Fecha/Hora:</strong> {{ $metadata['generated_at'] }}
                </td>
                <td width="33%" class="text-center">
                    <strong>Empresa:</strong> {{ $metadata['generated_by_company'] }}<br>
                    <strong>ID Viaje:</strong> {{ $metadata['voyage_id'] }}
                </td>
                <td width="33%" class="text-right">
                    <strong>Tipo de Reporte:</strong> {{ $metadata['report_type'] }}<br>
                    <strong>Código:</strong> {{ $metadata['report_code'] }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>