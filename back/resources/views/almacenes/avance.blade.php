@extends('almacenes.reporte')

@section('titulo', 'AVANCE DE LA REVISIÓN')

@section('contenido')
    @php
        $detalles = $data['detalles'];
        $cantidad = fn ($valor, $unidad) => number_format((float) $valor, $unidad === 'KG' ? 3 : 0);
    @endphp

    <table class="kpis">
        <tr>
            <td><div class="label">Productos revisados</div><div class="value">{{ $data['revisados'] }} <span class="sub">de {{ $data['total_productos'] }}</span></div></td>
            <td><div class="label">Cuadran</div><div class="value up">{{ $data['sin_diferencia'] }}</div></td>
            <td><div class="label">Con diferencia</div><div class="value down">{{ $data['con_diferencia'] }}</div></td>
            <td><div class="label">Valor de la diferencia</div><div class="value {{ $data['diferencia_valor'] < 0 ? 'down' : 'up' }}">Bs {{ number_format($data['diferencia_valor'], 2) }}</div></td>
            <td><div class="label">Sin contar</div><div class="value">{{ max(0, $data['total_productos'] - $data['revisados']) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
        <tr>
            <th style="width:4%">#</th><th style="width:10%">Código</th><th style="width:24%">Producto</th>
            <th style="width:12%">Contó</th><th class="r" style="width:8%">Sistema</th><th class="r" style="width:8%">Contado</th>
            <th class="r" style="width:8%">Diferencia</th><th class="c" style="width:9%">Resultado</th>
            <th class="r" style="width:9%">Valor Bs</th><th class="r" style="width:8%">Quedará en</th>
        </tr>
        </thead>
        <tbody>
        @forelse($detalles as $indice => $detalle)
            @php
                $diferencia = (float) $detalle->diferencia_actual;
                $clase = abs($diferencia) < 0.0005 ? '' : ($diferencia > 0 ? 'up' : 'down');
            @endphp
            <tr>
                <td>{{ $indice + 1 }}</td>
                <td>{{ $detalle->codigo }}</td>
                <td>{{ $detalle->nombre }}<div class="sub">{{ $detalle->unidad }}</div></td>
                <td>{{ $detalle->usuario_nombre ?: '—' }}</td>
                <td class="r">{{ $cantidad($detalle->stock_actual, $detalle->unidad) }}</td>
                <td class="r"><b>{{ $cantidad($detalle->cantidad, $detalle->unidad) }}</b></td>
                <td class="r {{ $clase }}">{{ $diferencia > 0 ? '+' : '' }}{{ $cantidad($diferencia, $detalle->unidad) }}</td>
                <td class="c {{ $clase }}">{{ abs($diferencia) < 0.0005 ? 'CUADRA' : ($diferencia > 0 ? 'SOBRANTE' : 'FALTANTE') }}</td>
                <td class="r {{ $clase }}">{{ number_format($diferencia * (float) $detalle->precio_compra, 2) }}</td>
                <td class="r">{{ $cantidad($detalle->stock_nuevo ?? $detalle->cantidad, $detalle->unidad) }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">Todavía no se contó ningún producto en esta revisión.</td></tr>
        @endforelse
        </tbody>
        @if($detalles->count())
            <tfoot>
            <tr>
                <td colspan="4">TOTALES ({{ $detalles->count() }} productos)</td>
                <td class="r">{{ number_format($detalles->sum(fn ($d) => (float) $d->stock_actual), 3) }}</td>
                <td class="r">{{ number_format($detalles->sum(fn ($d) => (float) $d->cantidad), 3) }}</td>
                <td class="r">{{ number_format($detalles->sum(fn ($d) => (float) $d->diferencia_actual), 3) }}</td>
                <td></td>
                <td class="r">Bs {{ number_format($data['diferencia_valor'], 2) }}</td>
                <td></td>
            </tr>
            </tfoot>
        @endif
    </table>

    @if(count($data['por_usuario']))
        <p class="sub" style="margin-top:8px">
            Quién contó:
            @foreach($data['por_usuario'] as $usuarioFila)
                {{ $usuarioFila['usuario'] }} ({{ $usuarioFila['productos'] }}){{ $loop->last ? '' : ' · ' }}
            @endforeach
        </p>
    @endif
    <p class="muted">Los {{ max(0, $data['total_productos'] - $data['revisados']) }} productos que nadie contó no se modifican al aplicar la revisión.</p>
@endsection
