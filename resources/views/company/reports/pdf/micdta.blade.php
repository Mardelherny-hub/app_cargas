<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MIC/DTA - {{ $voyage['voyage_number'] ?? 'N/A' }}</title>
    <style>
        @page { size: A4 landscape; margin: 15mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
        
        .header { margin-bottom: 20px; border-bottom: 3px solid #1E40AF; padding-bottom: 10px; }
        .header h1 { font-size: 16pt; color: #1E40AF; margin-bottom: 5px; }
        .header .subtitle { font-size: 11pt; color: #666; font-weight: bold; }
        
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 20%; font-weight: bold; padding: 3px 5px; background: #f3f4f6; }
        .info-value { display: table-cell; width: 30%; padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 8pt; }
        table thead { background-color: #1E40AF; color: white; }
        table th { padding: 6px 4px; text-align: left; font-weight: bold; border: 1px solid #1E40AF; }
        table td { padding: 5px 4px; border: 1px solid #ddd; vertical-align: top; }
        table tbody tr:nth-child(even) { background-color: #f9fafb; }
        table tfoot { background-color: #DBEAFE; font-weight: bold; }
        table tfoot td { padding: 6px 4px; border: 1px solid #1E40AF; }
        
        .shipment-header { background-color: #E0E7FF; padding: 8px; margin-top: 15px; margin-bottom: 8px; font-weight: bold; border-left: 4px solid #4F46E5; }
        
        .footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 7pt; color: #666; text-align: center; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    {{-- ENCABEZADO --}}
    <div class="header">
        <h1>{{ $company['legal_name'] }}</h1>
        <div class="subtitle">MANIFIESTO DE CARGA INTERNACIONAL - MIC/DTA</div>
        <div style="font-size: 8pt; color: #666; margin-top: 5px;">
            Declaración ante AFIP - Argentina | CUIT: {{ $company['tax_id'] }}
        </div>
    </div>

    {{-- INFORMACIÓN DEL VIAJE --}}
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Viaje N°:</div>
            <div class="info-value">{{ $voyage['voyage_number'] }}</div>
            <div class="info-label">Embarcación:</div>
            <div class="info-value">{{ $voyage['vessel_name'] }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">IMO:</div>
            <div class="info-value">{{ $voyage['imo_number'] ?: 'N/A' }}</div>
            <div class="info-label">Matrícula:</div>
            <div class="info-value">{{ $voyage['vessel_registration'] ?: 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Puerto Origen:</div>
            <div class="info-value">{{ $voyage['origin_port'] }} ({{ $voyage['origin_port_code'] }})</div>
            <div class="info-label">Puerto Destino:</div>
            <div class="info-value">{{ $voyage['destination_port'] }} ({{ $voyage['destination_port_code'] }})</div>
        </div>
        <div class="info-row">
            <div class="info-label">Aduana Origen:</div>
            <div class="info-value">{{ $voyage['origin_customs'] }} ({{ $voyage['origin_customs_code'] }})</div>
            <div class="info-label">Aduana Destino:</div>
            <div class="info-value">{{ $voyage['destination_customs'] }} ({{ $voyage['destination_customs_code'] }})</div>
        </div>
        <div class="info-row">
            <div class="info-label">Fecha Salida:</div>
            <div class="info-value">{{ $voyage['departure_date'] }}</div>
            <div class="info-label">Fecha Arribo Est.:</div>
            <div class="info-value">{{ $voyage['arrival_date'] }}</div>
        </div>
        @if($voyage['transshipment_port'])
        <div class="info-row">
            <div class="info-label">Puerto Transbordo:</div>
            <div class="info-value" colspan="3">{{ $voyage['transshipment_port'] }}</div>
        </div>
        @endif
    </div>

    {{-- DETALLE POR SHIPMENT --}}
    @foreach($shipments as $shipment)
        <div class="shipment-header">
            ENVÍO {{ $loop->iteration }} - {{ $shipment['shipment_number'] }} | Embarcación: {{ $shipment['vessel_name'] }}
            @if($shipment['origin_manifest_id'])
                | Manifiesto Origen: {{ $shipment['origin_manifest_id'] }}
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">BL N°</th>
                    <th style="width: 15%;">Cargador</th>
                    <th style="width: 15%;">Consignatario</th>
                    <th style="width: 12%;">Puerto Carga</th>
                    <th style="width: 12%;">Puerto Descarga</th>
                    <th style="width: 6%;">Bultos</th>
                    <th style="width: 8%;">Peso (Kg)</th>
                    <th style="width: 8%;">Volumen (m³)</th>
                    <th style="width: 14%;">Mercadería</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shipment['bills'] as $bill)
                <tr>
                    <td>{{ $bill['bill_number'] }}</td>
                    <td>
                        {{ $bill['shipper_name'] }}
                        @if($bill['shipper_tax_id'])
                            <br><small>{{ $bill['shipper_tax_id'] }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $bill['consignee_name'] }}
                        @if($bill['consignee_tax_id'])
                            <br><small>{{ $bill['consignee_tax_id'] }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $bill['loading_port'] }}
                        @if($bill['loading_customs_code'])
                            <br><small>Aduana: {{ $bill['loading_customs_code'] }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $bill['discharge_port'] }}
                        @if($bill['discharge_customs_code'])
                            <br><small>Aduana: {{ $bill['discharge_customs_code'] }}</small>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($bill['total_packages'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($bill['gross_weight_kg'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($bill['volume_m3'], 2, ',', '.') }}</td>
                    <td>
                        {{ $bill['cargo_description'] }}
                        @if($bill['is_consolidated'] === 'S')
                            <br><small><strong>CONSOLIDADO</strong></small>
                        @endif
                        @if($bill['is_transit_transshipment'] === 'S')
                            <br><small><strong>TRÁNSITO/TRANSBORDO</strong></small>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    {{-- TOTALES --}}
    <table>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right"><strong>TOTALES GENERALES:</strong></td>
                <td class="text-center">{{ number_format($totals['total_packages'], 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['total_gross_weight_kg'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($totals['total_volume_m3'], 2, ',', '.') }}</td>
                <td>{{ $totals['total_bills'] }} Conocimiento(s) | {{ $totals['total_shipments'] }} Envío(s)</td>
            </tr>
        </tfoot>
    </table>

    {{-- PIE DE PÁGINA --}}
    <div class="footer">
        <p>Documento generado el {{ $metadata['generated_at'] }} por {{ $metadata['generated_by'] }}</p>
        <p>{{ $metadata['report_type'] }} - {{ $company['legal_name'] }}</p>
    </div>
</body>
</html>