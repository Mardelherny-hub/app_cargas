<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Cargo Manifest - {{ $voyage['voyage_number'] }}</title>
<style>
    @page { size: A4 landscape; margin: 5mm 6mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: "DejaVu Sans Mono", "Courier New", monospace;
        font-size: 6.8pt;
        line-height: 1.15;
        color: #111;
    }
    .manifest-group + .manifest-group { page-break-before: always; }
    .title { text-align: center; font-size: 13pt; font-weight: bold; margin: 0 0 2mm; }
    table { width: 100%; border-collapse: collapse; }
    .header td { border: 0.45pt solid #222; padding: 1.2mm 1.5mm; vertical-align: top; }
    .label { display: block; font-size: 5.7pt; margin-bottom: .6mm; }
    .value { font-size: 7pt; font-weight: normal; }
    .manifest { margin-top: 1.5mm; table-layout: fixed; }
    .manifest > thead > tr > th, .manifest > tbody > tr > td { border: 0.45pt solid #222; padding: 1.2mm; vertical-align: top; }
    .manifest > thead > tr.column-header > th { text-align: center; font-weight: normal; font-size: 5.8pt; }
    .manifest > tbody > tr > td { font-size: 6.2pt; }
    .manifest > thead > tr.page-header-row > th.page-header-cell {
        border: 0;
        padding: 0 0 1.5mm;
        text-align: left;
        font-weight: normal;
    }
    .manifest .header td { padding: 1.2mm 1.5mm; }
    .parties { width: 24%; }
    .bl { width: 10%; text-align: center; }
    .marks { width: 17%; }
    .goods { width: 34%; }
    .weight { width: 8%; text-align: right; }
    .measure { width: 7%; text-align: right; }
    .party-name { font-weight: bold; }
    .small { font-size: 5.6pt; }
    .item { margin-bottom: 1.2mm; }
    .container-line { margin-bottom: .7mm; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
</style>
</head>
<body>
@forelse($port_groups as $group)
<section class="manifest-group">
<table class="manifest">
    <thead>
        <tr class="page-header-row">
            <th colspan="6" class="page-header-cell">
<div class="title">Cargo Manifest</div>

<table class="header">
    <tr>
        <td style="width:20%">
            <span class="label">Name of Ship</span>
            <span class="value">{{ $voyage['vessel_name'] }}</span>
        </td>
        <td style="width:12%">
            <span class="label">Voy.No.</span>
            <span class="value">{{ $voyage['voyage_number'] }}</span>
        </td>
        <td style="width:26%">
            <span class="label">Place of Receipt</span>
            <span class="value">{{ $group['loading_port'] }}</span>
        </td>
        <td style="width:11%">
            <span class="label">Arrival</span>
            <span class="value">{{ $voyage['actual_arrival_date'] ?: $voyage['estimated_arrival_date'] }}</span>
        </td>
        <td style="width:11%">
            <span class="label">Departure</span>
            <span class="value">{{ $voyage['departure_date'] }}</span>
        </td>
        <td style="width:7%">
            <span class="label">Page No.</span>
            <span class="value page-number-placeholder">&nbsp;</span>
        </td>
        <td rowspan="2" style="width:13%; text-align:center; vertical-align:middle">
            <div style="font-size:8pt; font-weight:bold">{{ $voyage['company_name'] }}</div>
            @if(!empty($voyage['company_commercial_name']))
                <div class="small">{{ $voyage['company_commercial_name'] }}</div>
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Nationality of Ship</span>
            <span class="value">{{ $voyage['vessel_flag'] ?: '' }}</span>
        </td>
        <td>
            <span class="label">Name of Master</span>
            <span class="value">{{ $voyage['captain_name'] }}</span>
        </td>
        <td>
            <span class="label">Port of Loading</span>
            <span class="value">{{ $group['loading_port'] }}</span>
        </td>
        <td>
            <span class="label">Port of Discharge</span>
            <span class="value">{{ $group['discharge_port'] }}</span>
        </td>
        <td colspan="2">
            <span class="label">Port of Destination</span>
            <span class="value">{{ $group['final_destination_port'] }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="6">
            <span class="label">Datos de Registro de la Embarcación</span>
            <span class="value">
                @if(!empty($voyage['vessel_registration'])) Matrícula: {{ $voyage['vessel_registration'] }} @endif
                @if(!empty($voyage['vessel_imo'])) &nbsp; IMO: {{ $voyage['vessel_imo'] }} @endif
            </span>
        </td>
        <td>
            <span class="label">Date of Sailing</span>
            <span class="value">{{ $voyage['departure_date'] }}</span>
        </td>
    </tr>
</table>
            </th>
        </tr>
        <tr class="column-header">
            <th class="parties">Shippers (Sh)<br>Consignee (Co)<br>Notify Address (No)</th>
            <th class="bl">B/L No.</th>
            <th class="marks">Marks &amp; Nos. (M)<br>Container Nos. (CN)<br>Seal Nos. (SN)</th>
            <th class="goods">No. &amp; Kind of Packages<br>Description of Goods</th>
            <th class="weight">Gross Wt.<br>Kgs</th>
            <th class="measure">Measur.<br>M3</th>
        </tr>
    </thead>
    <tbody>
    @foreach($group['bills'] as $bill)
        <tr>
            <td class="parties">
                <div><strong>Sh)</strong> <span class="party-name">{{ $bill['shipper']['company_name'] ?? $bill['shipper_name'] }}</span></div>
                @if(!empty($bill['shipper']['address']))<div>{{ $bill['shipper']['address'] }}</div>@endif
                @if(!empty($bill['shipper']['tax_id']))<div>CUIT/RUC: {{ $bill['shipper']['tax_id'] }}</div>@endif
                <br>
                <div><strong>Co)</strong> <span class="party-name">{{ $bill['consignee']['company_name'] ?? $bill['consignee_name'] }}</span></div>
                @if(!empty($bill['consignee']['address']))<div>{{ $bill['consignee']['address'] }}</div>@endif
                @if(!empty($bill['consignee']['tax_id']))<div>CUIT/RUC: {{ $bill['consignee']['tax_id'] }}</div>@endif
                <br>
                <div><strong>No)</strong> <span class="party-name">{{ $bill['notify']['company_name'] ?? 'No aplica' }}</span></div>
                @if(!empty($bill['notify']['address']))<div>{{ $bill['notify']['address'] }}</div>@endif
                @if(!empty($bill['notify']['tax_id']))<div>CUIT/RUC: {{ $bill['notify']['tax_id'] }}</div>@endif
            </td>
            <td class="bl">{{ $bill['bill_number'] }}</td>
            <td class="marks">
                @if(!empty($bill['cargo_marks']))
                    <div class="container-line">{{ $bill['cargo_marks'] }}</div>
                @endif
                @foreach($bill['containers'] as $container)
                    <div class="container-line">
                        {{ $container['number'] }}
                        @if(!empty($container['type'])) {{ $container['type'] }} @endif
                        @if(!empty($container['seals']))<br><span class="small">SEAL: {{ $container['seals'] }}</span>@endif
                    </div>
                @endforeach
            </td>
            <td class="goods">
                @if(count($bill['items']) > 0)
                    @foreach($bill['items'] as $item)
                        <div class="item">
                            @if(!empty($item['quantity'])){{ $item['quantity'] }} @endif
                            {{ $item['package_type'] ?: 'BULTOS' }}
                            @if(!empty($item['description']))<br>{{ $item['description'] }}@endif
                            @if(!empty($item['commodity_code']))<br>HS/NCM: {{ $item['commodity_code'] }}@endif
                            @if(!empty($item['net_weight_kg']))<br>NET WEIGHT: {{ number_format((float)$item['net_weight_kg'], 3, '.', '') }} KGS @endif
                        </div>
                    @endforeach
                @else
                    <div>{{ $bill['total_packages'] }} BULTOS</div>
                    <div>{{ $bill['cargo_description'] }}</div>
                    @if(!empty($bill['commodity_code']))<div>HS/NCM: {{ $bill['commodity_code'] }}</div>@endif
                @endif
                @if(!empty($bill['final_destination_port']) && $bill['final_destination_port'] !== $bill['discharge_port'])
                    <br><div>DESTINO FINAL: {{ $bill['final_destination_port'] }}</div>
                @endif
            </td>
            <td class="weight">{{ number_format((float)$bill['gross_weight_kg'], 3, '.', '') }}</td>
            <td class="measure">{{ number_format((float)$bill['volume_m3'], 3, '.', '') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</section>
@empty
<div style="padding:8mm;text-align:center">No hay conocimientos para el viaje seleccionado.</div>
@endforelse
</body>
</html>