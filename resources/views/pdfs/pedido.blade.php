<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Pedido #{{ $pedido->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .cliente-info {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            /* border: 1px solid #ddd; */
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        .totales {
            float: right;
            width: 300px;
        }

        .totales table {
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="">

    </div>
    <div class="header">
        <h1>Pedido #{{ $pedido->id }}</h1>
        <p>Fecha: {{ $fecha }}</p>
    </div>


    <div>
        <hr>
        <table>
            <tbody>
                <tr >
                    <td style="padding: 15px 0px;">Razon Social:</td>
                    <td style="padding: 15px 0px;">{{ $cliente->nombres }} {{ $cliente->apellidos }}</td>
                    <td style="padding: 15px 0px;">CI/RUC</td>
                    <td style="padding: 15px 0px;">{{ $cliente->ci }}</td>
                </tr>
                <tr>
                    <td >Fecha emisión: </td>
                    <td >{{ $pedido->fecha }}</td>
                </tr>
            </tbody>
        </table>
        <hr>
    </div>


    {{--<div class="cliente-info">
        <hr>
        <!-- <h3>Información del Cliente</h3> -->
        <p>Razon Social: {{ $cliente->nombres }} {{ $cliente->apellidos }}</p>
        <p>CI/RUC: {{ $cliente->ci }}</p>
        <p>Email: {{ $cliente->email }}</p>
        <hr>
    </div>--}}

    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($detalles as $detalle)
            <tr>
                <td>{{ $detalle->producto->nombre }}</td>
                <td>{{ $detalle->cantidad }}</td>
                <td>${{ number_format($detalle->precio_unitario, 2) }}</td>
                <td style="text-align: end; align-items: end;">${{ number_format($detalle->total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <table>
            <tr>
                <td><strong>Subtotal 0%:</strong></td>
                <td>${{ number_format($pedido->subtotal_0, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Subtotal IVA:</strong></td>
                <td>${{ number_format($pedido->subtotal_impuesto, 2) }}</td>
            </tr>
            <tr>
                <td><strong>IVA :</strong></td>
                <td>${{ number_format($pedido->iva, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Total:</strong></td>
                <td>${{ number_format($pedido->total, 2) }}</td>
            </tr>
        </table>
    </div>
</body>

</html>