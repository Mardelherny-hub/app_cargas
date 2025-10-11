<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carta de Aviso de Llegada - {{ $voyage['voyage_number'] ?? 'N/A' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }

        /* ENCABEZADO DE LA EMPRESA */
        .company-header {
            margin-bottom: 25px;
            border-bottom: 2px solid #1E40AF;
            padding-bottom: 15px;
        }

        .company-header h1 {
            font-size: 18pt;
            color: #1E40AF;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-header p {
            font-size: 9pt;
            color: #555;
            margin: 2px 0;
        }

        /* DATOS DEL DESTINATARIO */
        .recipient-section {
            margin-top: 30px;
            margin-bottom: 25px;
        }

        .recipient-section .date {
            text-align: right;
            font-size: 10pt;
            margin-bottom: 20px;
        }

        .recipient-section .recipient-data {
            margin-bottom: 20px;
        }

        .recipient-section .recipient-data p {
            margin: 3px 0;
            font-size: 10pt;
        }

        .recipient-section .recipient-data .name {
            font-weight: bold;
            font-size: 11pt;
        }

        /* CUERPO DE LA CARTA */
        .letter-body {
            text-align: justify;
            margin-bottom: 20px;
        }

        .letter-body .greeting {
            margin-bottom: 15px;
            font-weight: bold;
        }

        .letter-body p {
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .letter-body .highlight {
            font-weight: bold;
            color: #1E40AF;
        }

        /* TABLA DE MERCADERÍA */
        .cargo-section {
            margin: 25px 0;
        }

        .cargo-section h3 {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1E40AF;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt;
        }

        table thead {
            background-color: #1E40AF;
            color: white;
        }

        table th {
            padding: 8px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1E40AF;
        }

        table td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }

        table tfoot {
            background-color: #DBEAFE;
            font-weight: bold;
        }

        table tfoot td {
            padding: 8px 5px;
            border: 1px solid #1E40AF;
        }

        /* SECCIÓN INFORMATIVA */
        .info-box {
            background-color: #f3f4f6;
            border-left: 4px solid #1E40AF;
            padding: 12px;
            margin: 20px 0;
        }

        .info-box h4 {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 8px;
            color: #1E40AF;
        }

        .info-box p {
            margin: 4px 0;
            font-size: 10pt;
        }

        .info-box ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .info-box li {
            margin: 4px 0;
            font-size: 10pt;
        }

        /* CIERRE DE LA CARTA */
        .letter-closing {
            margin-top: 30px;
        }

        .letter-closing p {
            margin: 8px 0;
        }

        .signature-section {
            margin-top: 50px;
        }

        .signature-section p {
            font-weight: bold;
            margin: 3px 0;
        }

        /* PIE DE PÁGINA */
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }

        /* UTILIDADES */
        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .small {
            font-size: 9pt;
        }
    </style>
</head>
<body>
    {{-- ENCABEZADO DE LA EMPRESA EMISORA --}}
    <div class="company-header">
        <h1>{{ $company['legal_name'] ?? 'N/A' }}</h1>
        @if(!empty($company['commercial_name']))
            <p><strong>{{ $company['commercial_name'] }}</strong></p>
        @endif
        <p>{{ $company['full_address'] ?? '' }}</p>
        @if(!empty($company['email']))
            <p>Email: {{ $company['email'] }} | Tel: {{ $company['phone'] ?? 'N/A' }}</p>
        @endif
        @if(!empty($company['tax_id']))
            <p>CUIT/RUC: {{ $company['tax_id'] }}</p>
        @endif
    </div>

    {{-- DATOS DEL DESTINATARIO --}}
    <div class="recipient-section">
        <div class="date">
            {{ $metadata['generated_at'] ?? now()->format('d/m/Y') }}
        </div>

        <div class="recipient-data">
            <p class="name">Señores: {{ $consignee['legal_name'] ?? 'N/A' }}</p>
            @if(!empty($consignee['commercial_name']))
                <p>{{ $consignee['commercial_name'] }}</p>
            @endif
            <p>{{ $consignee['address'] ?? 'Dirección no disponible' }}</p>
            @if(!empty($consignee['email']))
                <p>Email: {{ $consignee['email'] }}</p>
            @endif
        </div>

        <p class="bold">Ref: Aviso de Llegada de Mercadería - Viaje {{ $voyage['voyage_number'] ?? 'N/A' }}</p>
    </div>

    {{-- CUERPO DE LA CARTA --}}
    <div class="letter-body">
        <p class="greeting">De nuestra mayor consideración:</p>

        <p>
            Por medio de la presente, tenemos el agrado de informarles que ha arribado mercadería a su consignación 
            en el medio de transporte <span class="highlight">{{ $voyage['vessel_name'] ?? 'N/A' }}</span>, 
            correspondiente al viaje <span class="highlight">{{ $voyage['voyage_number'] ?? 'N/A' }}</span>, 
            con arribo estimado el día <span class="highlight">{{ $voyage['estimated_arrival_date'] ?? 'N/A' }}</span>.
        </p>

        <p>
            La mercadería se encuentra disponible para su retiro en 
            <strong>{{ $voyage['destination_port_name'] ?? 'N/A' }}</strong>.
        </p>
    </div>

    {{-- TABLA DE MERCADERÍA --}}
    <div class="cargo-section">
        <h3>Detalle de Mercadería a su Consignación</h3>

        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">BL Number</th>
                    <th style="width: 25%;">Cargador</th>
                    <th style="width: 10%; text-align: center;">Bultos</th>
                    <th style="width: 12%; text-align: right;">Peso (Kg)</th>
                    <th style="width: 38%;">Descripción de Carga</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bills as $bill)
                <tr>
                    <td>{{ $bill['bill_number'] }}</td>
                    <td>{{ $bill['shipper_name'] }}</td>
                    <td style="text-align: center;">{{ number_format($bill['total_packages'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">{{ number_format($bill['gross_weight_kg'], 2, ',', '.') }}</td>
                    <td>{{ $bill['cargo_description'] }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" class="text-right">TOTALES:</td>
                    <td style="text-align: center;">{{ number_format($totals['total_packages'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">{{ number_format($totals['total_weight_kg'], 2, ',', '.') }}</td>
                    <td>{{ $totals['total_bills'] }} Conocimiento(s)</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- INFORMACIÓN DE RETIRO --}}
    <div class="info-box">
        <h4>LUGAR DE RETIRO</h4>
        <p>
            <strong>Puerto:</strong> {{ $voyage['destination_port_full_address'] ?? $voyage['destination_port_name'] ?? 'N/A' }}
        </p>
    </div>

    <div class="info-box">
        <h4>DOCUMENTACIÓN REQUERIDA PARA EL RETIRO</h4>
        <ul>
            <li>Original del Conocimiento de Embarque (Bill of Lading)</li>
            <li>Documento de identidad del autorizado</li>
            <li>Carta poder (en caso de retiro por terceros)</li>
            <li>Documentación aduanera correspondiente</li>
            <li>Comprobante de pago de gastos portuarios (si corresponde)</li>
        </ul>
    </div>

    {{-- CIERRE DE LA CARTA --}}
    <div class="letter-body letter-closing">
        <p>
            Quedamos a su disposición para cualquier consulta o aclaración que requieran.
        </p>

        <p>
            Sin otro particular, saludamos a ustedes muy atentamente.
        </p>
    </div>

    {{-- FIRMA --}}
    <div class="signature-section">
        <p>{{ $company['legal_name'] ?? 'N/A' }}</p>
        <p class="small">Departamento de Operaciones</p>
    </div>

    {{-- PIE DE PÁGINA --}}
    <div class="footer">
        <p>
            Documento generado el {{ $metadata['generated_at'] ?? now()->format('d/m/Y H:i:s') }} 
            por {{ $metadata['generated_by'] ?? 'Sistema' }}
        </p>
        <p class="small">
            Este es un documento informativo. Para cualquier trámite oficial, presentar la documentación original.
        </p>
    </div>
</body>
</html>