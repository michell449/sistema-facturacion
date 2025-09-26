<?php
$html = '
<head>
    <style>
        body {
            font-family: sans-serif;
            font-size: 10px;
        }

        .container {
            width: 100%;
        }

        .header,
        .footer {
            width: 100%;
        }

        .header {
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            font-size: 8px;
            text-align: center;
        }

        .col-6 {
            width: 50%;
            float: left;
        }

        .col-12 {
            width: 100%;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .card {
            border: 1px solid #eee;
            border-radius: 5px;
            margin-bottom: 10px;
            padding: 10px;
        }

        .card-header {
            font-weight: bold;
            background-color: #f8f8f8;
            padding: 5px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #ddd;
            padding: 5px;
        }

        .table th {
            background-color: #f2f2f2;
        }

        .sello {
            font-size: 7px;
            word-break: break-all;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="col-6" style="text-align:left;">
                <h2 style="margin:0;">' . htmlspecialchars($datosFactura['emisor_nombre']) . '</h2>
                <p style="margin:0;">RFC: ' . htmlspecialchars($datosFactura['emisor_rfc']) . '</p>
            </div>
            <div class="col-6" style="text-align:right;">
                <h3 style="margin:0;">FACTURA ELECTRÓNICA</h3>
                <p style="margin:0;"><strong>Folio Fiscal (UUID):</strong> ' . $datosFactura['uuid'] . '</p>
                <p style="margin:0;"><strong>Serie-Folio:</strong> ' . htmlspecialchars($datosFactura['serie'] . ' - ' . $datosFactura['folio']) . '</p>
                <p style="margin:0;"><strong>Fecha:</strong> ' . $datosFactura['fecha'] . '</p>
            </div>
        </div>

        <div class="col-12" style="margin-top:20px;">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">Receptor</div>
                    <strong>Nombre:</strong> ' . htmlspecialchars($datosFactura['receptor_nombre']) . '<br>
                    <strong>RFC:</strong> ' . htmlspecialchars($datosFactura['receptor_rfc']) . '<br>
                    <strong>Uso CFDI:</strong> ' . htmlspecialchars($datosFactura['receptor_uso_cfdi']) . '
                </div>
            </div>
            <div class="col-6">
                <div class="card">
                    <div class="card-header">Datos del Comprobante</div>
                    <strong>Moneda:</strong> ' . $datosFactura['moneda'] . '<br>
                    <strong>Forma de Pago:</strong> ' . $datosFactura['forma_pago'] . '<br>
                    <strong>Método de Pago:</strong> ' . $datosFactura['metodo_pago'] . '
                </div>
            </div>
        </div>

        <div class="col-12" style="margin-top:20px;">
            <h4>Conceptos</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>ClaveProdServ</th>
                        <th>Cant</th>
                        <th>ClaveUnidad</th>
                        <th>Descripción</th>
                        <th class="text-right">V. Unitario</th>
                        <th class="text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>' . $conceptosHtml . '</tbody>
            </table>
        </div>

        <div class="col-12" style="margin-top:10px;">
            <div class="col-6" style="float:right;">
                <p class="text-right"><strong>Subtotal:</strong> $' . number_format((float)$datosFactura['subtotal'], 2) . '</p>
                <p class="text-right"><strong>Total:</strong> $' . number_format((float)$datosFactura['total'], 2) . '</p>
            </div>
        </div>

        <div class="footer">
            <div class="col-12">
                <div class="col-6" style="text-align: left;">
                    <img src="' . $qrCode . '" width="100" />
                </div>
                <div class="col-6" style="text-align: right;">
                    <p class="sello"><strong>Sello Digital del CFDI:</strong><br>' . wordwrap($sello, 100, "<br />\n", true) . '</p>
                </div>
            </div>
        </div>

    </div>
</body>

</html>';