@extends('almacenes.reporte')

@section('titulo', 'CAPITAL EN ALMACÉN')

@section('contenido')
    @php
        $detalles = $data['detalles'];
        $resto = $data['no_revisados'];
        $cantidad = fn ($valor, $unidad) => number_format((float) $valor, $unidad === 'KG' ? 3 : 0);
        $bs = fn ($valor) => number_format((float) $valor, 2);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Capital hoy a costo</div><div class="value">Bs {{ $bs($data['costo_antes']) }}</div></td>
            <td><div class="label">Capital hoy a venta</div><div class="value">Bs {{ $bs($data['venta_antes']) }}</div></td>
            <td><div class="label">Capital tras la revisión (costo)</div><div class="value">Bs {{ $bs($data['costo_despues']) }}</div></td>
            <td><div class="label">Capital tras la revisión (venta)</div><div class="value">Bs {{ $bs($data['venta_despues']) }}</div></td>
            <td>
                <div class="label">Diferencia</div>
                <div class="value {{ $data['diferencia_costo'] < 0 ? 'down' : 'up' }}">Bs {{ $bs($data['diferencia_costo']) }}</div>
                <div class="sub">a venta Bs {{ $bs($data['diferencia_venta']) }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
        <tr>
            <th style="width:4%">#</th><th style="width:9%">Código</th><th style="width:20%">Producto</th>
            <th class="r" style="width:7%">P. compra</th><th class="r" style="width:7%">P. venta</th>
            <th class="r" style="width:7%">Stock hoy</th><th class="r" style="width:8%">Capital costo</th><th class="r" style="width:8%">Capital venta</th>
            <th class="r" style="width:7%">Stock después</th><th class="r" style="width:8%">Capital costo</th><th class="r" style="width:8%">Capital venta</th>
            <th class="r" style="width:7%">Dif. costo</th>
        </tr>
        </thead>
        <tbody>
        @forelse($detalles as $indice => $detalle)
            @php
                $compra = (float) $detalle->precio_compra;
                $venta = (float) ($detalle->producto?->precio_venta ?? 0);
                $antes = (float) $detalle->stock_actual;
                $despues = (float) $detalle->cantidad;
                $diferencia = (float) $detalle->diferencia_actual;
                $clase = abs($diferencia) < 0.0005 ? '' : ($diferencia > 0 ? 'up' : 'down');
            @endphp
            <tr>
                <td>{{ $indice + 1 }}</td>
                <td>{{ $detalle->codigo }}</td>
                <td>{{ $detalle->nombre }}<div class="sub">{{ $detalle->unidad }} · contó {{ $detalle->usuario_nombre ?: '—' }}</div></td>
                <td class="r">{{ $bs($compra) }}</td>
                <td class="r">{{ $bs($venta) }}</td>
                <td class="r">{{ $cantidad($antes, $detalle->unidad) }}</td>
                <td class="r">{{ $bs($antes * $compra) }}</td>
                <td class="r">{{ $bs($antes * $venta) }}</td>
                <td class="r"><b>{{ $cantidad($despues, $detalle->unidad) }}</b></td>
                <td class="r">{{ $bs($despues * $compra) }}</td>
                <td class="r">{{ $bs($despues * $venta) }}</td>
                <td class="r {{ $clase }}">{{ $bs($diferencia * $compra) }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="empty">Todavía no se contó ningún producto en esta revisión.</td></tr>
        @endforelse
        @if($resto['productos'] > 0)
            <tr>
                <td>{{ $detalles->count() + 1 }}</td>
                <td>—</td>
                <td class="muted">PRODUCTOS NO REVISADOS ({{ $resto['productos'] }})<div class="sub">No cambian con esta revisión</div></td>
                <td class="r">—</td><td class="r">—</td>
                <td class="r">{{ number_format($resto['cantidad'], 3) }}</td>
                <td class="r">{{ $bs($resto['costo']) }}</td>
                <td class="r">{{ $bs($resto['venta']) }}</td>
                <td class="r">{{ number_format($resto['cantidad'], 3) }}</td>
                <td class="r">{{ $bs($resto['costo']) }}</td>
                <td class="r">{{ $bs($resto['venta']) }}</td>
                <td class="r">0.00</td>
            </tr>
        @endif
        </tbody>
        <tfoot>
        <tr>
            <td colspan="5">CAPITAL TOTAL DE LA TIENDA</td>
            <td class="r"></td>
            <td class="r">Bs {{ $bs($data['costo_antes']) }}</td>
            <td class="r">Bs {{ $bs($data['venta_antes']) }}</td>
            <td class="r"></td>
            <td class="r">Bs {{ $bs($data['costo_despues']) }}</td>
            <td class="r">Bs {{ $bs($data['venta_despues']) }}</td>
            <td class="r {{ $data['diferencia_costo'] < 0 ? 'down' : 'up' }}">Bs {{ $bs($data['diferencia_costo']) }}</td>
        </tr>
        </tfoot>
    </table>

    <p class="muted">
        El precio de venta es el vigente del producto. El capital de hoy es el stock que tiene el sistema ahora;
        el de después es el que quedará cuando se aplique la revisión (los {{ $resto['productos'] }} productos que
        nadie contó entran igual en los dos totales).
    </p>
@endsection
