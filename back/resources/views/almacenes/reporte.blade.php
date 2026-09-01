<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 26px 20px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1f2937; }
        .head { border-bottom: 2px solid #f57c00; padding-bottom: 6px; margin-bottom: 8px; }
        .head h1 { margin: 0; font-size: 15px; letter-spacing: .3px; }
        .head h2 { margin: 2px 0 0; font-size: 10px; color: #f57c00; }
        .head .meta { margin-top: 3px; font-size: 8px; color: #6b7280; }
        .estado { float: right; font-size: 9px; font-weight: bold; border: 1px solid #f57c00;
                  color: #f57c00; padding: 3px 8px; border-radius: 9px; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin-bottom: 8px; }
        .kpis td { border: 1px solid #e5e7eb; border-radius: 5px; padding: 5px 7px; background: #fafafa; width: 20%; }
        .kpis .label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .kpis .value { font-size: 12px; font-weight: bold; margin-top: 1px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #171717; color: #fff; padding: 4px 3px; font-size: 7px;
                        text-align: left; border: 1px solid #171717; }
        table.data td { padding: 3px; border: 1px solid #e5e7eb; }
        table.data tbody tr:nth-child(even) { background: #fafafa; }
        table.data tfoot td { background: #fff3e0; font-weight: bold; border-top: 2px solid #f57c00; }
        .r { text-align: right; white-space: nowrap; }
        .c { text-align: center; }
        .sub { color: #6b7280; font-size: 7px; }
        .up { color: #15803d; }
        .down { color: #b91c1c; }
        .muted { color: #6b7280; font-style: italic; }
        .empty { padding: 16px; text-align: center; color: #6b7280; font-style: italic; }
        .foot { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #9ca3af;
                border-top: 1px solid #e5e7eb; padding-top: 3px; }
        .foot .pag { float: right; }
    </style>
</head>
<body>
<div class="head">
    <span class="estado">{{ $almacen->estado === 'BORRADOR' ? 'EN REVISIÓN' : $almacen->estado }}</span>
    <h1>{{ $empresa?->nombre_empresa ?: 'Bean' }}</h1>
    <h2>@yield('titulo') — REVISIÓN {{ $almacen->numero }}</h2>
    <div class="meta">
        {{ $almacen->descripcion ?: 'Revisión del stock físico de la tienda' }} ·
        Creada por {{ $almacen->usuario_nombre }} el {{ $almacen->fecha?->format('d/m/Y H:i') }}
        @if($almacen->estado === 'APLICADO')
            · Aplicada por {{ $almacen->aplicado_por_nombre ?: '—' }} el {{ $almacen->fecha_aplicado?->format('d/m/Y H:i') }}
        @endif
        <br>Generado: {{ now()->format('d/m/Y H:i') }} · Usuario: {{ $usuario ?: '—' }}
        @if($empresa?->nit) · NIT: {{ $empresa->nit }} @endif
    </div>
</div>

@yield('contenido')

<div class="foot">
    {{ $empresa?->nombre_empresa ?: 'Bean' }} — @yield('titulo') {{ $almacen->numero }}
    <span class="pag">{{ now()->format('d/m/Y H:i') }}</span>
</div>
</body>
</html>
