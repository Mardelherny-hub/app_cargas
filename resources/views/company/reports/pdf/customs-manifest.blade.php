{{-- 
  Vista PDF - Manifiesto Aduanero
  Ubicación: resources/views/reports/pdf/customs-manifest.blade.php
  
  Reporte oficial formato landscape para autoridades aduaneras
  Incluye códigos aduaneros y datos de transbordo
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifiesto Aduanero - {{ $voyage->voyage_number }}</title>
    <style>
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
        
        .header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid #dc2626;
        }
        
        .header-top {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .header-logo {
            display: table-cell;
            width: 15%;
            vertical-align: middle;
        }
        
        .header-logo img {
            max-width: 80px;
            max-height: 60px;
        }
        
        .header-title {
            display: table-cell;
            width: 70%;
            text-align: center;
            vertical-align: middle;
        }
        
        .header-title h1 {
            font-size: 18pt;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 3px;
        }
        
        .header-title h2 {
            font-size: 11pt;
            color: #666;
            font-weight: normal;
        }
        
        .header-date {
            display: table-cell;
            width: 15%;
            text-align: right;
            vertical-align: middle;
            font-size: 8pt;
            color: #666;
        }
        
        .company-info {
            background: #f3f4f6;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .company-info strong {
            color: #dc2626;
        }
        
        .info-section {
            margin-bottom: 12px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-cell {
            display: table-cell;
            padding: 6px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        .info-cell.label {
            background: #f9fafb;
            font-weight: bold;
            width: 20%;
            color: #374151;
        }
        
        .info-cell.value {
            width: 30%;
        }
        
        .section-title {
            background: #dc2626;
            color: white;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 10pt;
            margin: 15px 0 8px 0;
            border-radius: 3px;
        }
        
        .transshipment-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 8px;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }
        
        table.data-table thead {
            background: #374151;
            color: white;
        }
        
        table.data-table th {
            padding: 5px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1f2937;
        }
        
        table.data-table td {
            padding: 4px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        
        table.data-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        
        table.data-table tbody tr:hover {
            background: #f3f4f6;
        }
        
        .totals-box {
            background: #dbeafe;
            border: 2px solid #3b82f6;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        
        .totals-grid {
            display: table;
            width: 100%;
        }
        
        .totals-row {
            display: table-row;
        }
        
        .totals-cell {
            display: table-cell;
            padding: 4px 8px;
            font-weight: bold;
        }
        
        .totals-cell.label {
            color: #1e40af;
            width: 70%;
        }
        
        .totals-cell.value {
            text-align: right;
            color: #1e3a8a;
            font-size: 10pt;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            font-size: 7pt;
            color: #666;
            text-align: center;
        }
        
        .signatures {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        
        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 10px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 9pt;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-sm { font-size: 8pt; }
        .text-xs { font-size: 7pt; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>

    {{-- ENCABEZADO --}}
    <div class="header">
        <div class="header-top">
            <div class="header-logo">
                @if(isset($company_logo) && $company_logo)
                    <img src="{{ $company_logo }}" alt="Logo">
                @endif
            </div>
            <div class="header-title">
                <h1>MANIFIESTO ADUANERO</h1>
                <h2>Documento Oficial para Autoridades Aduaneras</h2>
            </div>
            <div class="header-date">
                Fecha: {{ $generation_date->format('d/m/Y') }}<br>
                Hora: {{ $generation_date->format('H:i') }}
            </div>
        </div>
        
        <div class="company-info">
            <strong>{{ $company->legal_name ?? $company->name }}</strong>
            @if($company->tax_id)
                | CUIT: {{ $company->tax_id }}
            @endif
            @if($company->address)
                | {{ $company->address }}
            @endif
        </div>
    </div>

    {{-- INFORMACIÓN DEL VIAJE --}}
    <div class="section-title">📋 INFORMACIÓN DEL VIAJE</div>
    
    <div class="info-grid">
        <div class="info-row">
            <div class="info-cell label">Nº Viaje:</div>
            <div class="info-cell value">{{ $voyage->voyage_number }}</div>
            <div class="info-cell label">Tipo de Viaje:</div>
            <div class="info-cell value">{{ ucfirst($voyage->voyage_type ?? 'N/A') }}</div>
        </div>
        <div class="info-row">
            <div class="info-cell label">Embarcación Principal:</div>
            <div class="info-cell value">
                {{ $voyage->leadVessel->name ?? 'N/A' }}
                @if($voyage->leadVessel && $voyage->leadVessel->registration_number)
                    ({{ $voyage->leadVessel->registration_number }})
                @endif
            </div>
            <div class="info-cell label">Bandera:</div>
            <div class="info-cell value">{{ $voyage->leadVessel->flag_country ?? 'N/A' }}</div>
        </div>
    </div>

    {{-- INFORMACIÓN DE PUERTOS Y ADUANAS --}}
    <div class="info-grid">
        <div class="info-row">
            <div class="info-cell label">Puerto Origen:</div>
            <div class="info-cell value">
                {{ $voyage->originPort->name ?? 'N/A' }} ({{ $voyage->originPort->code ?? '' }})
                <br><span class="text-xs">{{ $voyage->originPort->country->name ?? '' }}</span>
            </div>
            <div class="info-cell label">Aduana Origen:</div>
            <div class="info-cell value">
                {{ $voyage->originCustoms->name ?? 'N/A' }}
                @if($voyage->originCustoms && $voyage->originCustoms->code)
                    <br><span class="text-xs">Código: {{ $voyage->originCustoms->code }}</span>
                @endif
            </div>
        </div>
        <div class="info-row">
            <div class="info-cell label">Puerto Destino:</div>
            <div class="info-cell value">
                {{ $voyage->destinationPort->name ?? 'N/A' }} ({{ $voyage->destinationPort->code ?? '' }})
                <br><span class="text-xs">{{ $voyage->destinationPort->country->name ?? '' }}</span>
            </div>
            <div class="info-cell label">Aduana Destino:</div>
            <div class="info-cell value">
                {{ $voyage->destinationCustoms->name ?? 'N/A' }}
                @if($voyage->destinationCustoms && $voyage->destinationCustoms->code)
                    <br><span class="text-xs">Código: {{ $voyage->destinationCustoms->code }}</span>
                @endif
            </div>
        </div>
        <div class="info-row">
            <div class="info-cell label">Fecha Salida:</div>
            <div class="info-cell value">{{ $voyage->departure_date ? $voyage->departure_date->format('d/m/Y H:i') : 'N/A' }}</div>
            <div class="info-cell label">Arribo Estimado:</div>
            <div class="info-cell value">{{ $voyage->estimated_arrival_date ? $voyage->estimated_arrival_date->format('d/m/Y H:i') : 'N/A' }}</div>
        </div>
    </div>

    {{-- INFORMACIÓN DE TRANSBORDO (si aplica) --}}
    @if($transshipment_info)
        <div class="transshipment-warning">
            <strong>⚠️ VIAJE CON TRANSBORDO</strong><br>
            Puerto de Transbordo: 
            {{ $transshipment_info['transshipment_port']->name ?? 'N/A' }} 
            ({{ $transshipment_info['transshipment_port']->code ?? '' }})
            @if(isset($transshipment_info['origin_manifests']) && $transshipment_info['origin_manifests']->isNotEmpty())
                <br>Manifiestos de Origen: {{ $transshipment_info['origin_manifests']->implode(', ') }}
            @endif
        </div>
    @endif

    {{-- DETALLE DE CONOCIMIENTOS --}}
    <div class="section-title">📦 DETALLE DE CONOCIMIENTOS DE EMBARQUE</div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 7%;">Nº B/L</th>
                <th style="width: 12%;">Embarcador</th>
                <th style="width: 12%;">Consignatario</th>
                <th style="width: 9%;">Puerto Desc.</th>
                <th style="width: 8%;">Cód. Aduana</th>
                <th style="width: 5%;">Bultos</th>
                <th style="width: 6%;">Peso Bruto (kg)</th>
                <th style="width: 6%;">Peso Neto (kg)</th>
                <th style="width: 5%;">Vol. (m³)</th>
                <th style="width: 12%;">Tipo Carga</th>
                <th style="width: 18%;">Descripción</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bills_of_lading as $bl)
                <tr>
                    <td class="font-bold">{{ $bl->bill_number }}</td>
                    <td class="text-sm">
                        {{ $bl->shipper->legal_name ?? 'N/A' }}
                        @if($bl->shipper && $bl->shipper->tax_id)
                            <br><span class="text-xs">{{ $bl->shipper->tax_id }}</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        {{ $bl->consignee->legal_name ?? 'N/A' }}
                        @if($bl->consignee && $bl->consignee->tax_id)
                            <br><span class="text-xs">{{ $bl->consignee->tax_id }}</span>
                        @endif
                    </td>
                    <td class="text-sm">
                        {{ $bl->dischargePort->code ?? 'N/A' }}
                        <br><span class="text-xs">{{ $bl->dischargePort->name ?? '' }}</span>
                    </td>
                    <td class="text-center text-sm">
                        {{ $bl->discharge_customs_code ?? 'N/A' }}
                        @if($bl->dischargeCustoms && $bl->dischargeCustoms->code)
                            <br><span class="text-xs">{{ $bl->dischargeCustoms->code }}</span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($bl->total_packages ?? 0, 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($bl->gross_weight_kg ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($bl->net_weight_kg ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($bl->volume_m3 ?? 0, 3, ',', '.') }}</td>
                    <td class="text-sm">
                        {{ $bl->primaryCargoType->name ?? 'N/A' }}
                        @if($bl->commodity_code)
                            <br><span class="text-xs">NCM: {{ $bl->commodity_code }}</span>
                        @endif
                    </td>
                    <td class="text-xs">
                        {{ Str::limit($bl->cargo_description ?? 'Sin descripción', 80) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px;">
                        No hay conocimientos de embarque para mostrar
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- TOTALES --}}
    <div class="totals-box">
        <div class="totals-grid">
            <div class="totals-row">
                <div class="totals-cell label">TOTAL DE CONOCIMIENTOS:</div>
                <div class="totals-cell value">{{ $totals['total_bills'] }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-cell label">TOTAL DE BULTOS:</div>
                <div class="totals-cell value">{{ number_format($totals['total_packages'], 0, ',', '.') }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-cell label">TOTAL PESO BRUTO:</div>
                <div class="totals-cell value">{{ number_format($totals['total_gross_weight'], 2, ',', '.') }} kg</div>
            </div>
            <div class="totals-row">
                <div class="totals-cell label">TOTAL PESO NETO:</div>
                <div class="totals-cell value">{{ number_format($totals['total_net_weight'], 2, ',', '.') }} kg</div>
            </div>
            <div class="totals-row">
                <div class="totals-cell label">TOTAL VOLUMEN:</div>
                <div class="totals-cell value">{{ number_format($totals['total_volume'], 3, ',', '.') }} m³</div>
            </div>
        </div>
    </div>

    {{-- FIRMAS --}}
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">
                Firma y Sello Transportista
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Firma y Sello Autoridad Aduanera
            </div>
        </div>
    </div>

    {{-- PIE DE PÁGINA --}}
    <div class="footer">
        <p>
            <strong>Manifiesto Aduanero</strong> - Viaje: {{ $voyage->voyage_number }} | 
            Generado: {{ $generation_date->format('d/m/Y H:i:s') }} | 
            Usuario: {{ $generated_by }}
        </p>
        <p class="text-xs" style="margin-top: 3px;">
            Este documento es de carácter oficial y debe ser presentado ante las autoridades aduaneras competentes.
        </p>
    </div>

</body>
</html>