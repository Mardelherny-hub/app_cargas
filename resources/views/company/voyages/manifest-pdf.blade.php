<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manifiesto de Carga - {{ $voyage->voyage_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        
        .header h2 {
            font-size: 14px;
            margin: 0;
            color: #666;
        }
        
        .voyage-info {
            margin-bottom: 20px;
        }
        
        .voyage-info table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .voyage-info td {
            padding: 5px;
            vertical-align: top;
        }
        
        .voyage-info .label {
            font-weight: bold;
            width: 150px;
        }
        
        .shipment-section {
            margin-bottom: 25px;
            border: 1px solid #ddd;
            padding: 15px;
        }
        
        .shipment-header {
            background-color: #f5f5f5;
            padding: 8px;
            margin: -15px -15px 15px -15px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            font-size: 13px;
        }
        
        .bill-of-lading {
            margin-bottom: 20px;
            border-left: 3px solid #007bff;
            padding-left: 10px;
        }
        
        .bill-header {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 8px;
            color: #007bff;
        }
        
        .bill-details table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        .bill-details td {
            padding: 3px 5px;
            border-bottom: 1px solid #eee;
        }
        
        .bill-details .label {
            font-weight: bold;
            width: 120px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        
        .items-table td.number {
            text-align: right;
        }
        
        .summary {
            margin-top: 30px;
            border-top: 2px solid #333;
            padding-top: 15px;
        }
        
        .summary table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .summary td {
            padding: 5px;
            font-weight: bold;
        }
        
        .summary .total {
            font-size: 14px;
            background-color: #f8f9fa;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Encabezado del Manifiesto --}}
    <div class="header">
        <h1>MANIFIESTO DE CARGA</h1>
        <h2>{{ $voyage->company->legal_name }}</h2>
    </div>

    {{-- Información General del Viaje --}}
    <div class="voyage-info">
        <table>
            <tr>
                <td class="label">Número de Viaje:</td>
                <td><strong>{{ $voyage->voyage_number }}</strong></td>
                <td class="label">Fecha de Emisión:</td>
                <td>{{ now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="label">Embarcación Principal:</td>
                <td>{{ $voyage->leadVessel->name ?? 'N/A' }}</td>
                <td class="label">Capitán:</td>
                <td>{{ $voyage->captain->full_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Puerto de Origen:</td>
                <td>{{ $voyage->originPort->name }} ({{ $voyage->originCountry->name }})</td>
                <td class="label">Puerto de Destino:</td>
                <td>{{ $voyage->destinationPort->name }} ({{ $voyage->destinationCountry->name }})</td>
            </tr>
            <tr>
                <td class="label">Fecha de Partida:</td>
                <td>{{ $voyage->departure_date?->format('d/m/Y') ?? 'No definida' }}</td>
                <td class="label">Fecha Est. Llegada:</td>
                <td>{{ $voyage->estimated_arrival_date?->format('d/m/Y') ?? 'No definida' }}</td>
            </tr>
            @if($voyage->transshipmentPort)
            <tr>
                <td class="label">Puerto de Transbordo:</td>
                <td colspan="3">{{ $voyage->transshipmentPort->name }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Tipo de Viaje:</td>
                <td>{{ ucfirst($voyage->voyage_type) }}</td>
                <td class="label">Estado:</td>
                <td>{{ ucfirst($voyage->status) }}</td>
            </tr>
        </table>
    </div>

    {{-- Lista de Envíos y Conocimientos --}}
    @foreach($voyage->shipments as $shipmentIndex => $shipment)
        <div class="shipment-section">
            <div class="shipment-header">
                ENVÍO {{ $shipmentIndex + 1 }}: {{ $shipment->shipment_number }} 
                - {{ $shipment->vessel->name ?? 'Sin embarcación' }}
                @if($shipment->captain)
                    - Cap. {{ $shipment->captain->full_name }}
                @endif
            </div>

            @forelse($shipment->billsOfLading as $blIndex => $bl)
                <div class="bill-of-lading">
                    <div class="bill-header">
                        CONOCIMIENTO {{ $blIndex + 1 }}: {{ $bl->bill_number }}
                        @if($bl->verified_at)
                            <span style="color: green;">[VERIFICADO]</span>
                        @else
                            <span style="color: orange;">[PENDIENTE]</span>
                        @endif
                    </div>

                    <div class="bill-details">
                        <table>
                            <tr>
                                <td class="label">Fecha Conocimiento:</td>
                                <td>{{ $bl->bill_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                <td class="label">Fecha de Carga:</td>
                                <td>{{ $bl->loading_date?->format('d/m/Y') ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Cargador:</td>
                                <td>{{ $bl->shipper->legal_name ?? 'N/A' }}</td>
                                <td class="label">Tax ID:</td>
                                <td>{{ $bl->shipper->tax_id ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <td class="label">Consignatario:</td>
                                <td>{{ $bl->consignee->legal_name ?? 'N/A' }}</td>
                                <td class="label">Tax ID:</td>
                                <td>{{ $bl->consignee->tax_id ?? 'N/A' }}</td>
                            </tr>
                            @if($bl->notifyParty)
                            <tr>
                                <td class="label">Notificar a:</td>
                                <td colspan="3">{{ $bl->notifyParty->legal_name }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="label">Términos de Flete:</td>
                                <td>{{ ucfirst($bl->freight_terms) }}</td>
                                <td class="label">Moneda:</td>
                                <td>{{ $bl->currency_code }}</td>
                            </tr>
                        </table>
                    </div>

                    {{-- Ítems del Conocimiento --}}
                    @if($bl->shipmentItems->count() > 0)
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">Item</th>
                                    <th style="width: 35%;">Descripción</th>
                                    <th style="width: 15%;">Tipo de Carga</th>
                                    <th style="width: 10%;">Embalaje</th>
                                    <th style="width: 8%;">Bultos</th>
                                    <th style="width: 12%;">Peso Bruto (kg)</th>
                                    <th style="width: 10%;">Volumen (m³)</th>
                                    <th style="width: 5%;">Cont.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bl->shipmentItems as $item)
                                <tr>
                                    <td class="number">{{ $item->line_number }}</td>
                                    <td>{{ $item->item_description }}</td>
                                    <td>{{ $item->cargoType->name ?? 'N/A' }}</td>
                                    <td>{{ $item->packagingType->name ?? 'N/A' }}</td>
                                    <td class="number">{{ number_format($item->package_quantity) }}</td>
                                    <td class="number">{{ number_format($item->gross_weight_kg, 2) }}</td>
                                    <td class="number">{{ number_format($item->volume_m3 ?? 0, 3) }}</td>
                                    <td class="number">{{ $item->containers->count() }}</td>
                                </tr>
                                @endforeach
                                {{-- Totales del BL --}}
                                <tr style="background-color: #f8f9fa; font-weight: bold;">
                                    <td colspan="4">TOTAL CONOCIMIENTO {{ $bl->bill_number }}</td>
                                    <td class="number">{{ number_format($bl->shipmentItems->sum('package_quantity')) }}</td>
                                    <td class="number">{{ number_format($bl->shipmentItems->sum('gross_weight_kg'), 2) }}</td>
                                    <td class="number">{{ number_format($bl->shipmentItems->sum('volume_m3'), 3) }}</td>
                                    <td class="number">{{ $bl->shipmentItems->sum(function($item) { return $item->containers->count(); }) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    @else
                        <p style="font-style: italic; color: #666;">Este conocimiento no tiene ítems registrados.</p>
                    @endif
                </div>
            @empty
                <p style="font-style: italic; color: #666;">Este envío no tiene conocimientos de embarque.</p>
            @endforelse
        </div>

        {{-- Salto de página entre shipments (excepto el último) --}}
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    {{-- Resumen Total del Viaje --}}
    <div class="summary">
        <h3 style="margin-top: 0;">RESUMEN TOTAL DEL VIAJE</h3>
        <table>
            <tr>
                <td style="width: 25%;">Total de Envíos:</td>
                <td style="width: 25%;"><strong>{{ $voyage->shipments->count() }}</strong></td>
                <td style="width: 25%;">Total de Conocimientos:</td>
                <td style="width: 25%;"><strong>{{ $voyage->shipments->sum(function($s) { return $s->billsOfLading->count(); }) }}</strong></td>
            </tr>
            <tr>
                <td>Total de Ítems:</td>
                <td><strong>{{ $voyage->shipments->sum(function($s) { return $s->billsOfLading->sum(function($bl) { return $bl->shipmentItems->count(); }); }) }}</strong></td>
                <td>Total de Contenedores:</td>
                <td><strong>{{ $voyage->shipments->sum(function($s) { return $s->billsOfLading->sum(function($bl) { return $bl->shipmentItems->sum(function($item) { return $item->containers->count(); }); }); }) }}</strong></td>
            </tr>
            <tr class="total">
                <td>Peso Total (kg):</td>
                <td><strong>{{ number_format($voyage->shipments->sum(function($s) { return $s->billsOfLading->sum(function($bl) { return $bl->shipmentItems->sum('gross_weight_kg'); }); }), 2) }}</strong></td>
                <td>Volumen Total (m³):</td>
                <td><strong>{{ number_format($voyage->shipments->sum(function($s) { return $s->billsOfLading->sum(function($bl) { return $bl->shipmentItems->sum('volume_m3'); }); }), 3) }}</strong></td>
            </tr>
        </table>
    </div>

    {{-- Pie de página --}}
    <div class="footer">
        Manifiesto generado el {{ now()->format('d/m/Y \a \l\a\s H:i') }} por {{ auth()->user()->name ?? 'Sistema' }} | 
        {{ $voyage->company->legal_name }} | 
        Página {PAGE_NUM} de {PAGE_COUNT}
    </div>
</body>
</html>