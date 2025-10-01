<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento de Embarque - {{ $billOfLading->bill_number }}</title>
    <style>
        @page {
            margin: 10mm 15mm;
            size: A4 portrait;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1a1a1a;
            padding: 10px 20px;
            margin: 0 30px;
        }
        
        /* Header con gradiente */
        .header {
            background: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .company-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: 600;
            margin: 3px 0;
            opacity: 0.95;
        }
        
        .bill-number {
            font-size: 18px;
            font-weight: bold;
            background: white;
            color: #dc2626;
            padding: 5px 15px;
            display: inline-block;
            border-radius: 4px;
            margin-top: 8px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 8px;
        }
        
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-verified { background: #d1fae5; color: #065f46; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        
        /* Secciones con diseño mejorado */
        .section {
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        
        .section-header {
            background: linear-gradient(to right, #f3f4f6, #e5e7eb);
            padding: 8px 15px;
            font-weight: bold;
            font-size: 11px;
            color: #374151;
            border-bottom: 2px solid #3b82f6;
            letter-spacing: 0.5px;
        }
        
        .section-content {
            padding: 12px 15px;
            background: white;
        }
        
        /* Grid mejorado */
        .row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .row-2 {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .row-full {
            grid-template-columns: 1fr;
        }
        
        .field {
            background: #f9fafb;
            padding: 8px 10px;
            border-radius: 4px;
            border-left: 3px solid #3b82f6;
        }
        
        .label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }
        
        .value {
            font-size: 10px;
            color: #1f2937;
            font-weight: 500;
        }
        
        /* Tabla mejorada */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9px;
        }
        
        .table thead {
            background: linear-gradient(to bottom, #3b82f6, #2563eb);
            color: white;
        }
        
        .table th {
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 9px;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        
        .table th:last-child {
            border-right: none;
        }
        
        .table td {
            padding: 7px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        
        .table tbody tr:nth-child(even) {
            background: #fafafa;
        }
        
        /* Cuadros de partes */
        .party-box {
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            min-height: 80px;
        }
        
        .party-title {
            font-size: 9px;
            text-transform: uppercase;
            color: #3b82f6;
            font-weight: bold;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3b82f6;
        }
        
        .party-name {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .party-details {
            font-size: 9px;
            color: #6b7280;
            line-height: 1.5;
        }
        
        /* Firmas */
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        
        .signature-box {
            text-align: center;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .signature-line {
            border-bottom: 2px solid #374151;
            height: 50px;
            margin-bottom: 8px;
        }
        
        .signature-title {
            font-weight: bold;
            font-size: 10px;
            color: #1f2937;
            margin-bottom: 3px;
        }
        
        .signature-subtitle {
            font-size: 8px;
            color: #6b7280;
        }
        
        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 12px;
            border-top: 2px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
        }
        
        .footer-info {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .footer-note {
            text-align: center;
            font-style: italic;
            padding: 8px;
            background: #f9fafb;
            border-radius: 4px;
            border-left: 3px solid #3b82f6;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-purple { background: #ede9fe; color: #6b21a8; }
        .badge-orange { background: #fed7aa; color: #92400e; }
        
        /* AFIP destacado */
        .afip-section {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px dashed #10b981;
        }
        
        .afip-section .section-header {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
            border-bottom: none;
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    {{-- HEADER --}}
    <div class="header">
        <div class="company-name">{{ $billOfLading->shipment->voyage->company->legal_name ?? 'EMPRESA DE TRANSPORTE' }}</div>
        <div class="document-title">CONOCIMIENTO DE EMBARQUE</div>
        <div class="document-title" style="opacity: 0.9;">BILL OF LADING</div>
        <div class="bill-number">N° {{ $billOfLading->bill_number }}</div>
        <div>
            <span class="status-badge status-{{ $billOfLading->status }}">
                {{ strtoupper($billOfLading->status) }}
            </span>
        </div>
    </div>

    {{-- INFORMACIÓN DEL VIAJE --}}
    <div class="section">
        <div class="section-header">INFORMACION DEL VIAJE</div>
        <div class="section-content">
            <div class="row">
                <div class="field">
                    <div class="label">Envio</div>
                    <div class="value">{{ $billOfLading->shipment->shipment_number ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Viaje</div>
                    <div class="value">{{ $billOfLading->shipment->voyage->voyage_number ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Buque/Barcaza</div>
                    <div class="value">{{ $billOfLading->shipment->voyage->vessel->name ?? '-' }}</div>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <div class="label">Fecha Conocimiento</div>
                    <div class="value">{{ $billOfLading->bill_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Fecha Carga</div>
                    <div class="value">{{ $billOfLading->loading_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Fecha Descarga</div>
                    <div class="value">{{ $billOfLading->discharge_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- PARTES INVOLUCRADAS --}}
    <div class="section">
        <div class="section-header">PARTES INVOLUCRADAS</div>
        <div class="section-content">
            <div class="row row-2">
                <div class="party-box">
                    <div class="party-title">Cargador/Exportador</div>
                    <div class="party-name">{{ $billOfLading->shipper->legal_name ?? '-' }}</div>
                    <div class="party-details">
                        @if($billOfLading->shipper)
                            Tax ID: {{ $billOfLading->shipper->tax_id }}<br>
                            Pais: {{ $billOfLading->shipper->country->name ?? '-' }}
                        @endif
                    </div>
                </div>
                <div class="party-box">
                    <div class="party-title">Consignatario/Importador</div>
                    <div class="party-name">{{ $billOfLading->consignee->legal_name ?? '-' }}</div>
                    <div class="party-details">
                        @if($billOfLading->consignee)
                            Tax ID: {{ $billOfLading->consignee->tax_id }}<br>
                            Pais: {{ $billOfLading->consignee->country->name ?? '-' }}
                        @endif
                    </div>
                </div>
            </div>
            
            @if($billOfLading->notifyParty || $billOfLading->cargoOwner)
            <div class="row row-2" style="margin-top: 10px;">
                @if($billOfLading->notifyParty)
                <div class="party-box">
                    <div class="party-title">Parte a Notificar</div>
                    <div class="party-name">{{ $billOfLading->notifyParty->legal_name }}</div>
                    <div class="party-details">Tax ID: {{ $billOfLading->notifyParty->tax_id }}</div>
                </div>
                @endif
                @if($billOfLading->cargoOwner)
                <div class="party-box">
                    <div class="party-title">Propietario de Carga</div>
                    <div class="party-name">{{ $billOfLading->cargoOwner->legal_name }}</div>
                    <div class="party-details">Tax ID: {{ $billOfLading->cargoOwner->tax_id }}</div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- RUTAS Y PUERTOS --}}
    <div class="section">
        <div class="section-header">RUTAS Y PUERTOS</div>
        <div class="section-content">
            <div class="row row-2">
                <div class="field">
                    <div class="label">Puerto de Carga</div>
                    <div class="value">
                        {{ $billOfLading->loadingPort->name ?? '-' }}
                        @if($billOfLading->loadingPort && $billOfLading->loadingPort->country)
                            <br><span style="font-size: 8px; color: #6b7280;">{{ $billOfLading->loadingPort->country->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="field">
                    <div class="label">Puerto de Descarga</div>
                    <div class="value">
                        {{ $billOfLading->dischargePort->name ?? '-' }}
                        @if($billOfLading->dischargePort && $billOfLading->dischargePort->country)
                            <br><span style="font-size: 8px; color: #6b7280;">{{ $billOfLading->dischargePort->country->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            
            @if($billOfLading->transshipmentPort || $billOfLading->finalDestinationPort)
            <div class="row row-2">
                @if($billOfLading->transshipmentPort)
                <div class="field">
                    <div class="label">Puerto de Transbordo</div>
                    <div class="value">
                        {{ $billOfLading->transshipmentPort->name }}
                        @if($billOfLading->transshipmentPort->country)
                            <br><span style="font-size: 8px; color: #6b7280;">{{ $billOfLading->transshipmentPort->country->name }}</span>
                        @endif
                    </div>
                </div>
                @endif
                @if($billOfLading->finalDestinationPort)
                <div class="field">
                    <div class="label">Destino Final</div>
                    <div class="value">
                        {{ $billOfLading->finalDestinationPort->name }}
                        @if($billOfLading->finalDestinationPort->country)
                            <br><span style="font-size: 8px; color: #6b7280;">{{ $billOfLading->finalDestinationPort->country->name }}</span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- DATOS AFIP (DESTACADO) --}}
    @if($billOfLading->origin_location || $billOfLading->origin_country_code || $billOfLading->origin_loading_date || $billOfLading->destination_country_code || $billOfLading->discharge_customs_code || $billOfLading->operational_discharge_code)
    <div class="section afip-section">
        <div class="section-header">DATOS AFIP ORIGEN/DESTINO</div>
        <div class="section-content">
            <div class="row">
                @if($billOfLading->origin_location)
                <div class="field" style="background: white;">
                    <div class="label">Lugar de Origen</div>
                    <div class="value">{{ $billOfLading->origin_location }}</div>
                </div>
                @endif
                @if($billOfLading->origin_country_code)
                <div class="field" style="background: white;">
                    <div class="label">Pais Lugar Origen</div>
                    <div class="value"><span class="badge badge-blue">{{ $billOfLading->origin_country_code }}</span></div>
                </div>
                @endif
                @if($billOfLading->origin_loading_date)
                <div class="field" style="background: white;">
                    <div class="label">Fecha Carga Origen</div>
                    <div class="value">{{ \Carbon\Carbon::parse($billOfLading->origin_loading_date)->format('d/m/Y H:i') }}</div>
                </div>
                @endif
            </div>
            <div class="row">
                @if($billOfLading->destination_country_code)
                <div class="field" style="background: white;">
                    <div class="label">Pais Destino</div>
                    <div class="value"><span class="badge badge-green">{{ $billOfLading->destination_country_code }}</span></div>
                </div>
                @endif
                @if($billOfLading->discharge_customs_code)
                <div class="field" style="background: white;">
                    <div class="label">Aduana Descarga</div>
                    <div class="value"><span class="badge badge-purple">{{ $billOfLading->discharge_customs_code }}</span></div>
                </div>
                @endif
                @if($billOfLading->operational_discharge_code)
                <div class="field" style="background: white;">
                    <div class="label">Lugar Operativo</div>
                    <div class="value"><span class="badge badge-orange">{{ $billOfLading->operational_discharge_code }}</span></div>
                </div>
                @endif
            </div>
            <div style="text-align: center; margin-top: 8px; font-size: 8px; color: #059669; font-style: italic;">
                Campos para webservice AFIP RegistrarTitulosCbc
            </div>
        </div>
    </div>
    @endif

    {{-- INFORMACIÓN DE CARGA --}}
    <div class="section">
        <div class="section-header">INFORMACION DE CARGA</div>
        <div class="section-content">
            <div class="row">
                <div class="field">
                    <div class="label">Tipo de Carga</div>
                    <div class="value">{{ $billOfLading->primaryCargoType->name ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Embalaje</div>
                    <div class="value">{{ $billOfLading->primaryPackagingType->name ?? '-' }}</div>
                </div>
                <div class="field">
                    <div class="label">Total Bultos</div>
                    <div class="value font-bold">{{ number_format($billOfLading->total_packages ?? 0) }}</div>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <div class="label">Peso Bruto (kg)</div>
                    <div class="value font-bold">{{ number_format($billOfLading->gross_weight_kg ?? 0, 2) }}</div>
                </div>
                <div class="field">
                    <div class="label">Peso Neto (kg)</div>
                    <div class="value font-bold">{{ number_format($billOfLading->net_weight_kg ?? 0, 2) }}</div>
                </div>
                <div class="field">
                    <div class="label">Volumen (m³)</div>
                    <div class="value font-bold">{{ number_format($billOfLading->volume_m3 ?? 0, 3) }}</div>
                </div>
            </div>
            @if($billOfLading->cargo_description)
            <div class="row row-full">
                <div class="field">
                    <div class="label">Descripcion de la Carga</div>
                    <div class="value">{{ $billOfLading->cargo_description }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- DETALLE DE MERCADERÍAS --}}
    @if($billOfLading->shipment && $billOfLading->shipment->shipmentItems->count() > 0)
    <div class="section">
        <div class="section-header">DETALLE DE MERCADERIAS ({{ $billOfLading->shipment->shipmentItems->count() }} items)</div>
        <div class="section-content">
            <table class="table">
                <thead>
                    <tr>
                        <th width="5%">Linea</th>
                        <th width="40%">Descripcion</th>
                        <th width="12%">Tipo</th>
                        <th width="8%">Bultos</th>
                        <th width="12%">Peso Bruto</th>
                        <th width="12%">Peso Neto</th>
                        <th width="11%">Volumen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billOfLading->shipment->shipmentItems as $item)
                    <tr>
                        <td class="text-center font-bold">{{ $item->line_number }}</td>
                        <td>
                            <strong>{{ $item->item_description }}</strong>
                            @if($item->item_reference)
                                <br><span style="font-size: 8px; color: #6b7280;">Ref: {{ $item->item_reference }}</span>
                            @endif
                        </td>
                        <td>{{ $item->cargoType->name ?? '-' }}</td>
                        <td class="text-right">{{ number_format($item->package_quantity ?? 0) }}</td>
                        <td class="text-right">{{ number_format($item->gross_weight_kg ?? 0, 2) }} kg</td>
                        <td class="text-right">{{ number_format($item->net_weight_kg ?? 0, 2) }} kg</td>
                        <td class="text-right">{{ number_format($item->volume_m3 ?? 0, 3) }} m³</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- TÉRMINOS COMERCIALES --}}
    <div class="section">
        <div class="section-header">TERMINOS COMERCIALES</div>
        <div class="section-content">
            <div class="row">
                <div class="field">
                    <div class="label">Flete</div>
                    <div class="value">{{ strtoupper($billOfLading->freight_terms) }}</div>
                </div>
                <div class="field">
                    <div class="label">Pago</div>
                    <div class="value">{{ strtoupper($billOfLading->payment_terms) }}</div>
                </div>
                <div class="field">
                    <div class="label">Moneda</div>
                    <div class="value">{{ $billOfLading->currency_code }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FIRMAS --}}
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-title">CARGADOR</div>
            <div class="signature-subtitle">Firma y Sello</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-title">TRANSPORTISTA</div>
            <div class="signature-subtitle">Firma y Sello</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-title">CONSIGNATARIO</div>
            <div class="signature-subtitle">Firma y Sello</div>
        </div>
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div class="footer-info">
            <div><strong>Conocimiento de Embarque N° {{ $billOfLading->bill_number }}</strong></div>
            <div>Generado el {{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div class="footer-note">
            Este documento constituye el conocimiento de embarque oficial segun las normativas de transporte fluvial y maritimo.<br>
            <strong>{{ $billOfLading->shipment->voyage->company->legal_name ?? 'Sistema de Gestion de Cargas' }}</strong>
        </div>
    </div>
</body>
</html>