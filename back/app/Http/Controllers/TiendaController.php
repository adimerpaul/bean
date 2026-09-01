<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Producto;
use Illuminate\Http\Request;

/**
 * Tienda virtual pública (ruta /tienda del frontend). No pide sesión: es la
 * cartilla que ve el cliente para armar su pedido y mandarlo por WhatsApp.
 * Sólo expone datos de vitrina —nombre, unidad, precio de venta y foto—; nunca
 * el precio de compra ni las existencias exactas, sólo si hay o no disponible.
 */
class TiendaController extends Controller
{
    /** Número al que llegan los pedidos de la tienda (formato internacional, sin +). */
    private const WHATSAPP = '59165417608';

    /** Cabecera de la tienda: datos del negocio, WhatsApp y categorías con productos. */
    public function show()
    {
        $empresa = Configuracion::firstOrCreate([], ['nombre_empresa' => 'Bean']);
        $categorias = Categoria::query()
            ->whereIn('id', $this->visibleQuery()->select('categoria_id'))
            ->orderBy('nombre')->get(['id', 'nombre']);

        return response()->json([
            'empresa' => [
                'nombre' => $empresa->nombre_empresa,
                'direccion' => $empresa->direccion,
                'telefono' => $empresa->telefono,
                'logo' => $empresa->logo,
            ],
            'whatsapp' => self::WHATSAPP,
            'categorias' => $categorias,
            'total_productos' => $this->visibleQuery()->count(),
        ]);
    }

    public function productos(Request $request)
    {
        $query = $this->visibleQuery()->with('categoriaRelacion:id,nombre');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhere('categoria', 'like', "%{$search}%"));
        }
        if ($categoria = $request->integer('categoria_id')) {
            $query->where('categoria_id', $categoria);
        }
        if ($request->boolean('solo_disponibles')) {
            $query->where('stock_inicial', '>', 0);
        }

        // Lo que no hay se manda al final: la vitrina muestra primero lo que se puede vender.
        match ($request->input('orden')) {
            'precio_asc' => $query->orderBy('precio_venta'),
            'precio_desc' => $query->orderByDesc('precio_venta'),
            default => $query->orderByRaw('stock_inicial <= 0')->orderBy('nombre'),
        };

        $productos = $query->paginate(min(max((int) $request->input('per_page', 24), 1), 60));
        $productos->getCollection()->transform(fn ($producto) => [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'categoria' => $producto->categoriaRelacion?->nombre ?? $producto->categoria,
            'unidad' => $producto->unidad,
            'precio_venta' => (float) $producto->precio_venta,
            'foto' => $producto->foto,
            'disponible' => (float) $producto->stock_inicial > 0,
        ]);

        return response()->json($productos);
    }

    /** Sólo se publica lo que tiene precio de venta cargado. */
    private function visibleQuery()
    {
        return Producto::query()->where('precio_venta', '>', 0);
    }
}
