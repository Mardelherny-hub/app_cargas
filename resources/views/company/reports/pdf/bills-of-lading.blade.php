<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Conocimientos</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8pt; line-height: 1.2; color: #000; }

        .header { margin-bottom: 10px; border-bottom: 2px solid #2563eb; padding-bottom: 8px; }
        .header-top { display: table; width: 100%; margin-bottom: 6px; }
        .header-logo { display: table-cell; width: 20%; vertical-align: middle; }
        .header-logo img { max-height: 40px; max-width: 120px; }
        .header-title { display: table-cell; width: 60%; text-align: center; vertical-align: middle; }
        .header-title h1 { font-size: 16pt; font-weight: bold; color: #1e40af; margin-bottom: 2px; }
        .header-title h2 { font-size: 11pt; font-weight: normal; color: #374151; }
        .header-company { display: table-cell; width: 20%; text-align: right; vertical-align: middle; font-size: 7pt; }
        .header-company strong { display: block; font-size: 9pt; color: #1f2937; margin-bottom: 2px; }

        .info-section { background-color: #f3f4f6; padding: 6px; margin-bottom: 8px; border-radius: 3px; }
        .info-section table { width: 100%; border-collapse: collapse; }
        .info-section td { padding: 2px 6px; font-size: 7pt; }
        .info-section td strong { color: #374151; font-weight: bold; }

        .filters-section { background-color: #dbeafe; padding: 4px 6px; margin-bottom: 8px; border-radius: 3px; font-size: 7pt; }
        .filters-section strong { color: #1e40af; }

        .bills-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 7pt; }
        .bills-table thead { background-color: #1e40af; color: white; }
        .bills-table thead th { padding: 4px 3px; text-align: left; font-weight: bold; font-size: 7pt; border: 1px solid #1e3a8a; }
        .bills-table tbody td { padding: 3px; border: 1px solid #d1d5db; vertical-align: top; }
        .bills-table tbody tr:nth-child(even) { background-color: #f9fafb; }

        .col-num { width: 3%; text-align: center; }
        .col-bl { width: 10%; font-weight: bold; }
        .col-date { width: 7%; }
        .col-voyage { width: 8%; }
        .col-client { width: 16%; }
        .col-port { width: 12%; font-size: 6pt; }
        .col-qty { width: 5%; text-align: right; }
        .col-weight { width: 7%; text-align: right; }
        .col-status { width: 7%; text-align: center; }

        .totals-section { background-color: #dbeafe; border: 2px solid #2563eb; padding: 6px; margin-bottom: 8px; border-radius: 3px; }
        .totals-table { width: 100%; border-collapse: collapse; }
        .totals-table td { padding: 3px 6px; font-size: 8pt; }
        .totals-table .label { font-weight: bold; color: #1e40af; width: 70%; }
        .totals-table .value { text-align: right; font-weight: bold; color: #1f2937; font-size: 9pt; }

        .footer { margin-top: 10px; padding-top: 6px; border-top: 1px solid #d1d5db; font-size: 6pt; color: #6b7280; }
        .footer table { width: 100%; }
        .footer td { padding: 1px 0; }

        .badge { display: inline-block; padding: 1px 4px; border-radius: 2px; font-size: 6pt; font-weight: bold; }
        .badge-draft { background-color: #fef3c7; color: #92400e; }
        .badge-verified { background-color: #d1fae5; color: #065f46; }
        .badge-sent { background-color: #dbeafe; color: #1e40af; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-small { font-size: 6pt; }
        .text-muted { color: #6b7280; }
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-top">
            <div class="header-logo">
                @if(isset($company_logo) && $company_logo)
                    <img src="{{ $company_logo }}" alt="Logo">
                @else
                    <div style="font-size: 10pt; font-weight: bold; color: #2563eb;">
                        {{ $company['commercial_name'] ?? $company['legal_name'] }}
                    </div>
                @endif
            </div>
            
            <div class="header-title">
                <h1>LISTADO DE CONOCIMIENTOS</h1>
                <h2>{{ $metadata['period'] }}</h2>
            </div>
            
            <div class="header-company">
                <strong>{{ $company['legal_name'] }}</strong>
                @if($company['tax_id'])
                    CUIT/RUC: {{ $company['tax_id'] }}<br>
                @endif
                Fecha: {{ $metadata['generated_at'] }}
            </div>
        </div>
    </div>

    @if(!empty($filters) && count($filters) > 0)
    <div class="filters-section">
        <strong>Filtros aplicados:</strong> {{ implode(' | ', $filters) }}
    </div>
    @endif

    <table class="bills-table">
        <thead>
            <tr>
                <th class="col-num">#</th>
                <th class="col-bl">BL Number</th>
                <th class="col-date">Fecha</th>
                <th class="col-voyage">Viaje</th>
                <th class="col-client">Cargador</th>
                <th class="col-client">Consignatario</th>
                <th class="col-port">Puertos</th>
                <th class="col-qty">Bultos</th>
                <th class="col-weight">Peso (kg)</th>
                <th class="col-status">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills_of_lading as $bill)
                <tr>
                    <td class="col-num">{{ $bill['line_number'] }}</td>
                    <td class="col-bl">
                        {{ $bill['bill_number'] }}
                        @if($bill['contains_dangerous_goods'])
                            <span class="badge badge-danger">⚠</span>
                        @endif
                    </td>
                    <td class="col-date">{{ $bill['bill_date'] }}</td>
                    <td class="col-voyage text-small">
                        {{ $bill['voyage_number'] }}<br>
                        {{ $bill['vessel_name'] }}
                    </td>
                    <td class="col-client">
                        <strong>{{ $bill['shipper_name'] }}</strong><br>
                        @if($bill['shipper_tax_id'])
                            <span class="text-small text-muted">{{ $bill['shipper_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="col-client">
                        <strong>{{ $bill['consignee_name'] }}</strong><br>
                        @if($bill['consignee_tax_id'])
                            <span class="text-small text-muted">{{ $bill['consignee_tax_id'] }}</span>
                        @endif
                    </td>
                    <td class="col-port">
                        <strong>Carga:</strong> {{ $bill['loading_port'] }}<br>
                        <strong>Desc:</strong> {{ $bill['discharge_port'] }}
                    </td>
                    <td class="col-qty">{{ number_format($bill['total_packages'], 0, ',', '.') }}</td>
                    <td class="col-weight">{{ number_format($bill['gross_weight_kg'], 2, ',', '.') }}</td>
                    <td class="col-status">
                        @php
                            $statusClass = match($bill['status']) {
                                'draft' => 'badge-draft',
                                'verified' => 'badge-verified',
                                'sent_to_customs' => 'badge-sent',
                                default => 'badge-draft'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $bill['status_label'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="padding: 15px;">
                        No hay conocimientos que coincidan con los filtros aplicados
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals-section no-break">
        <table class="totals-table">
            <tr>
                <td class="label">TOTAL DE CONOCIMIENTOS:</td>
                <td class="value">{{ number_format($totals['total_bills'], 0, ',', '.') }}</td>
                <td class="label">TOTAL BULTOS:</td>
                <td class="value">{{ number_format($totals['total_packages'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">PESO BRUTO (kg):</td>
                <td class="value">{{ number_format($totals['total_gross_weight_kg'], 2, ',', '.') }}</td>
                <td class="label">PESO BRUTO (tons):</td>
                <td class="value">{{ number_format($totals['total_gross_weight_tons'], 3, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="label">VOLUMEN TOTAL (m³):</td>
                <td class="value">{{ number_format($totals['total_volume_m3'], 2, ',', '.') }}</td>
                <td class="label">CARGADORES ÚNICOS:</td>
                <td class="value">{{ $totals['unique_shippers'] }}</td>
            </tr>
        </table>
    </div>

    @if(!empty($totals['by_status']))
    <div class="info-section no-break">
        <table>
            <tr>
                <td colspan="4"><strong>Distribución por Estado:</strong></td>
            </tr>
            <tr>
                @foreach($totals['by_status'] as $status => $count)
                    <td>{{ ucfirst($status) }}: <strong>{{ $count }}</strong></td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td width="33%">
                    <strong>Generado por:</strong> {{ $metadata['generated_by'] }}<br>
                    <strong>Fecha/Hora:</strong> {{ $metadata['generated_at'] }}
                </td>
                <td width="33%" class="text-center">
                    <strong>Empresa:</strong> {{ $metadata['generated_by_company'] }}<br>
                    <strong>Registros:</strong> {{ $metadata['record_count'] }}
                </td>
                <td width="33%" class="text-right">
                    <strong>Tipo:</strong> {{ $metadata['report_type'] }}<br>
                    <strong>Código:</strong> {{ $metadata['report_code'] }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>