<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conocimiento de Embarque - {{ $billOfLading->bill_number }}</title>
    <style>
        @page {
            margin: 15mm;
            size: A4;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .bill-number {
            font-size: 14px;
            font-weight: bold;
            color: #dc2626;
        }
        
        .section {
            margin-bottom: 15px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        
        .section-header {
            background-color: #f3f4f6;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 12px;
            border-bottom: 1px solid #d1d5db;
        }
        
        .section-content {
            padding: 10px 12px;
        }
        
        .row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .col {
            flex: 1;
            padding-right: 15px;
        }
        
        .col:last-child {
            padding-right: 0;
        }
        
        .col-1 { flex: 0 0 33.333333%; }
        .col-2 { flex: 0 0 66.666667%; }
        .col-full { flex: 0 0 100%; }
        
        .label {
            font-weight: bold;
            margin-bottom: 2px;
            color: #374151;
        }
        
        .value {
            color: #1f2937;
            min-height: 16px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th,
        .table td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        
        .table th {
            background-color: #f9fafb;
            font-weight: bold;
            font-size: 10px;
        }
        
        .table td {
            font-size: 10px;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-draft { background-color: #fef3c7; color: #92400e; }
        .status-verified { background-color: #d1fae5; color: #065f46; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #d1d5db;
            font-size: 10px;
            color: #6b7280;
        }
        
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 200px;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            height: 50px;
            margin-bottom: 5px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            
            body {
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    {{-- Encabezado --}}
    <div class="header">
        <div class="company-name">
            {{ $billOfLading->shipment->voyage->company->legal_name ?? 'Empresa de Transporte' }}
        </div>
        <div class="document-title">CONOCIMIENTO DE EMBARQUE</div>
        <div class="document-title">BILL OF LADING</div>
        <div class="bill-number">N° {{ $billOfLading->bill_number }}</div>
        
        <div style="margin-top: 10px;">
            <span class="status status-{{ $billOfLading->status }}">
                {{ ucfirst($billOfLading->status) }}
            </span>
        </div>
    </div>

    {{-- Información del Viaje --}}
    <div class="section">
        <div class="section-header">INFORMACIÓN DEL VIAJE</div>
        <div class="section-content">
            <div class="row">
                <div class="col col-1">
                    <div class="label">Envío:</div>
                    <div class="value">{{ $billOfLading->shipment->shipment_number ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Viaje:</div>
                    <div class="value">{{ $billOfLading->shipment->voyage->voyage_number ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Buque/Barcaza:</div>
                    <div class="value">{{ $billOfLading->shipment->voyage->vessel->name ?? '-' }}</div>
                </div>
            </div>
            <div class="row">
                <div class="col col-1">
                    <div class="label">Fecha Conocimiento:</div>
                    <div class="value">{{ $billOfLading->bill_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Fecha Carga:</div>
                    <div class="value">{{ $billOfLading->loading_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Fecha Descarga:</div>
                    <div class="value">{{ $billOfLading->discharge_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Partes Involucradas --}}
    <div class="section">
        <div class="section-header">PARTES INVOLUCRADAS</div>
        <div class="section-content">
            <div class="row">
                <div class="col">
                    <div class="label">CARGADOR/EXPORTADOR:</div>
                    <div class="value">
                        <strong>{{ $billOfLading->shipper->legal_name ?? '-' }}</strong><br>
                        @if($billOfLading->shipper)
                            Tax ID: {{ $billOfLading->shipper->tax_id }}<br>
                            País: {{ $billOfLading->shipper->country->name ?? '-' }}
                        @endif
                    </div>
                </div>
                <div class="col">
                    <div class="label">CONSIGNATARIO/IMPORTADOR:</div>
                    <div class="value">
                        <strong>{{ $billOfLading->consignee->legal_name ?? '-' }}</strong><br>
                        @if($billOfLading->consignee)
                            Tax ID: {{ $billOfLading->consignee->tax_id }}<br>
                            País: {{ $billOfLading->consignee->country->name ?? '-' }}
                        @endif
                    </div>
                </div>
            </div>
            
            @if($billOfLading->notifyParty || $billOfLading->cargoOwner)
            <div class="row">
                @if($billOfLading->notifyParty)
                <div class="col">
                    <div class="label">PARTE A NOTIFICAR:</div>
                    <div class="value">
                        <strong>{{ $billOfLading->notifyParty->legal_name }}</strong><br>
                        Tax ID: {{ $billOfLading->notifyParty->tax_id }}
                    </div>
                </div>
                @endif
                
                @if($billOfLading->cargoOwner)
                <div class="col">
                    <div class="label">PROPIETARIO DE LA CARGA:</div>
                    <div class="value">
                        <strong>{{ $billOfLading->cargoOwner->legal_name }}</strong><br>
                        Tax ID: {{ $billOfLading->cargoOwner->tax_id }}
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Rutas y Puertos --}}
    <div class="section">
        <div class="section-header">RUTAS Y PUERTOS</div>
        <div class="section-content">
            <div class="row">
                <div class="col">
                    <div class="label">PUERTO DE CARGA:</div>
                    <div class="value">
                        {{ $billOfLading->loadingPort->name ?? '-' }}
                        @if($billOfLading->loadingPort && $billOfLading->loadingPort->country)
                            <br>{{ $billOfLading->loadingPort->country->name }}
                        @endif
                    </div>
                </div>
                <div class="col">
                    <div class="label">PUERTO DE DESCARGA:</div>
                    <div class="value">
                        {{ $billOfLading->dischargePort->name ?? '-' }}
                        @if($billOfLading->dischargePort && $billOfLading->dischargePort->country)
                            <br>{{ $billOfLading->dischargePort->country->name }}
                        @endif
                    </div>
                </div>
            </div>
            
            @if($billOfLading->transshipmentPort || $billOfLading->finalDestinationPort)
            <div class="row">
                @if($billOfLading->transshipmentPort)
                <div class="col">
                    <div class="label">PUERTO DE TRANSBORDO:</div>
                    <div class="value">
                        {{ $billOfLading->transshipmentPort->name }}
                        @if($billOfLading->transshipmentPort->country)
                            <br>{{ $billOfLading->transshipmentPort->country->name }}
                        @endif
                    </div>
                </div>
                @endif
                
                @if($billOfLading->finalDestinationPort)
                <div class="col">
                    <div class="label">DESTINO FINAL:</div>
                    <div class="value">
                        {{ $billOfLading->finalDestinationPort->name }}
                        @if($billOfLading->finalDestinationPort->country)
                            <br>{{ $billOfLading->finalDestinationPort->country->name }}
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Información de Carga --}}
    <div class="section">
        <div class="section-header">INFORMACIÓN DE CARGA</div>
        <div class="section-content">
            <div class="row">
                <div class="col col-1">
                    <div class="label">Tipo de Carga:</div>
                    <div class="value">{{ $billOfLading->primaryCargoType->name ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Tipo de Embalaje:</div>
                    <div class="value">{{ $billOfLading->primaryPackagingType->name ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Total Paquetes:</div>
                    <div class="value">{{ number_format($billOfLading->total_packages ?? 0) }}</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col col-1">
                    <div class="label">Peso Bruto (kg):</div>
                    <div class="value">{{ number_format($billOfLading->gross_weight_kg ?? 0, 2) }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Peso Neto (kg):</div>
                    <div class="value">{{ number_format($billOfLading->net_weight_kg ?? 0, 2) }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Volumen (m³):</div>
                    <div class="value">{{ number_format($billOfLading->total_volume ?? 0, 3) }}</div>
                </div>
            </div>
            
            @if($billOfLading->cargo_description)
            <div class="row">
                <div class="col col-full">
                    <div class="label">DESCRIPCIÓN DE LA CARGA:</div>
                    <div class="value">{{ $billOfLading->cargo_description }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Detalle de Items del Envío --}}
    @if($billOfLading->shipment && $billOfLading->shipment->shipmentItems->count() > 0)
    <div class="section">
        <div class="section-header">DETALLE DE MERCADERÍAS</div>
        <div class="section-content">
            <table class="table">
                <thead>
                    <tr>
                        <th width="5%">Línea</th>
                        <th width="35%">Descripción</th>
                        <th width="15%">Tipo Carga</th>
                        <th width="10%">Embalaje</th>
                        <th width="8%">Cantidad</th>
                        <th width="12%">Peso Bruto (kg)</th>
                        <th width="12%">Peso Neto (kg)</th>
                        <th width="8%">Vol. (m³)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billOfLading->shipment->shipmentItems as $item)
                    <tr>
                        <td class="text-center">{{ $item->line_number }}</td>
                        <td>
                            {{ $item->item_description }}
                            @if($item->cargo_marks)
                                <br><small><em>Marcas: {{ $item->cargo_marks }}</em></small>
                            @endif
                            @if($item->commodity_code)
                                <br><small><strong>NCM: {{ $item->commodity_code }}</strong></small>
                            @endif
                        </td>
                        <td>{{ $item->cargoType->name ?? '-' }}</td>
                        <td>{{ $item->packagingType->name ?? '-' }}</td>
                        <td class="text-right">{{ number_format($item->package_quantity) }}</td>
                        <td class="text-right">{{ number_format($item->gross_weight_kg, 2) }}</td>
                        <td class="text-right">{{ number_format($item->net_weight_kg ?? 0, 2) }}</td>
                        <td class="text-right">{{ number_format($item->volume_m3 ?? 0, 3) }}</td>
                    </tr>
                    @endforeach
                    
                    {{-- Totales --}}
                    <tr style="background-color: #f9fafb; font-weight: bold;">
                        <td colspan="4" class="text-center">TOTALES</td>
                        <td class="text-right">{{ number_format($billOfLading->shipment->shipmentItems->sum('package_quantity')) }}</td>
                        <td class="text-right">{{ number_format($billOfLading->shipment->shipmentItems->sum('gross_weight_kg'), 2) }}</td>
                        <td class="text-right">{{ number_format($billOfLading->shipment->shipmentItems->sum('net_weight_kg'), 2) }}</td>
                        <td class="text-right">{{ number_format($billOfLading->shipment->shipmentItems->sum('volume_m3'), 3) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Términos Comerciales --}}
    <div class="section">
        <div class="section-header">TÉRMINOS COMERCIALES</div>
        <div class="section-content">
            <div class="row">
                <div class="col col-1">
                    <div class="label">Términos de Flete:</div>
                    <div class="value">{{ ucfirst($billOfLading->freight_terms ?? '-') }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Incoterms:</div>
                    <div class="value">{{ $billOfLading->incoterms ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Moneda:</div>
                    <div class="value">{{ $billOfLading->currency_code ?? 'USD' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Instrucciones y Observaciones --}}
    @if($billOfLading->special_instructions || $billOfLading->internal_notes)
    <div class="section">
        <div class="section-header">INSTRUCCIONES Y OBSERVACIONES</div>
        <div class="section-content">
            @if($billOfLading->special_instructions)
            <div class="row">
                <div class="col col-full">
                    <div class="label">INSTRUCCIONES ESPECIALES:</div>
                    <div class="value">{{ $billOfLading->special_instructions }}</div>
                </div>
            </div>
            @endif
            
            @if($billOfLading->internal_notes)
            <div class="row">
                <div class="col col-full">
                    <div class="label">NOTAS INTERNAS:</div>
                    <div class="value">{{ $billOfLading->internal_notes }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Información de Control --}}
    <div class="section">
        <div class="section-header">INFORMACIÓN DE CONTROL</div>
        <div class="section-content">
            <div class="row">
                <div class="col col-1">
                    <div class="label">Creado por:</div>
                    <div class="value">{{ $billOfLading->createdByUser->name ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Fecha Creación:</div>
                    <div class="value">{{ $billOfLading->created_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Última Actualización:</div>
                    <div class="value">{{ $billOfLading->updated_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
            </div>
            
            @if($billOfLading->verified_at)
            <div class="row">
                <div class="col col-1">
                    <div class="label">Verificado por:</div>
                    <div class="value">{{ $billOfLading->verifiedByUser->name ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Fecha Verificación:</div>
                    <div class="value">{{ $billOfLading->verified_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                <div class="col col-1">
                    <div class="label">Estado:</div>
                    <div class="value">
                        <span class="status status-{{ $billOfLading->status }}">
                            {{ ucfirst($billOfLading->status) }}
                        </span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Firmas --}}
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><strong>CARGADOR</strong></div>
            <div>Firma y Sello</div>
        </div>
        
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><strong>TRANSPORTISTA</strong></div>
            <div>Firma y Sello</div>
        </div>
        
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><strong>CONSIGNATARIO</strong></div>
            <div>Firma y Sello</div>
        </div>
    </div>

    {{-- Pie de página --}}
    <div class="footer">
        <div class="row">
            <div class="col">
                <strong>Conocimiento de Embarque N° {{ $billOfLading->bill_number }}</strong>
            </div>
            <div class="col text-right">
                Generado el {{ now()->format('d/m/Y H:i') }}
            </div>
        </div>
        
        <div style="margin-top: 10px; text-align: center; font-size: 9px;">
            Este documento constituye el conocimiento de embarque oficial según las normativas de transporte fluvial y marítimo.
            <br>
            {{ $billOfLading->shipment->voyage->company->legal_name ?? 'Sistema de Gestión de Cargas' }}
        </div>
    </div>

    {{-- Botón de impresión (solo en vista web) --}}
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
        <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;">
            🖨️ Imprimir
        </button>
        <button onclick="window.close()" style="background: #6b7280; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; margin-left: 5px;">
            ✖️ Cerrar
        </button>
    </div>
</body>
</html>