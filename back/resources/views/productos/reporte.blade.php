<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #263238; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        .meta { margin-bottom: 10px; color: #607d8b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfd8dc; padding: 4px; }
        th { background: #ef6c00; color: white; text-align: left; }
        .number { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #fff8f2; }
    </style>
</head>
<body>
    <h1>Inventario de productos</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }} · {{ $productos->count() }} productos</div>
    <table>
        <thead><tr><th>Código</th><th>Código barras</th><th>Producto</th><th>Categoría</th><th>Unidad</th><th>P. compra</th><th>P. venta</th><th>Stock</th></tr></thead>
        <tbody>
        @foreach($productos as $producto)
            <tr>
                <td>{{ $producto->codigo }}</td><td>{{ $producto->codigo_barras }}</td><td>{{ $producto->nombre }}</td>
                <td>{{ $producto->categoriaRelacion?->nombre ?? $producto->categoria }}</td><td>{{ $producto->unidad }}</td>
                <td class="number">Bs {{ number_format((float) $producto->precio_compra, 2) }}</td>
                <td class="number">Bs {{ number_format((float) $producto->precio_venta, 2) }}</td>
                <td class="number">{{ number_format((float) $producto->stock_inicial, $producto->unidad === 'KG' ? 3 : 0) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
