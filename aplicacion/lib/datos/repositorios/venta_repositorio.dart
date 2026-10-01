import 'package:sqflite/sqflite.dart';

import '../../modelos/item_carrito.dart';
import '../../modelos/venta.dart';
import '../servicios/api_servicio.dart';
import '../servicios/base_datos.dart';

class VentaRepositorio {
  VentaRepositorio(this._api);

  final ApiServicio _api;

  /// Registra la venta en el servidor y la cachea localmente.
  Future<Venta> registrar({
    required List<ItemCarrito> items,
    required double descuento,
    required String tipoPago,
    required double montoEfectivo,
    required double montoQr,
    String? observacion,
  }) async {
    final respuesta = await _api.publicar('/ventas', cuerpo: {
      'descuento': descuento,
      'tipo_pago': tipoPago,
      'monto_efectivo': montoEfectivo,
      'monto_qr': montoQr,
      if (observacion != null && observacion.isNotEmpty) 'observacion': observacion,
      'detalles': items
          .map((item) => {
                'producto_id': item.productoId,
                'cantidad': item.cantidad,
                'precio_venta': item.precioVenta,
              })
          .toList(),
    }) as Map<String, dynamic>;

    final venta = Venta.desdeApi(respuesta);
    await guardarEnCache([venta]);
    return venta;
  }

  /// Ventas del servidor. `pagina` empieza en 1.
  Future<List<Venta>> listar({
    String? busqueda,
    DateTime? desde,
    DateTime? hasta,
    int pagina = 1,
    int porPagina = 20,
  }) async {
    final respuesta = await _api.obtener('/ventas', parametros: {
      'q': busqueda,
      'desde': desde == null ? null : _soloFecha(desde),
      'hasta': hasta == null ? null : _soloFecha(hasta),
      'page': pagina,
      'per_page': porPagina,
    }) as Map<String, dynamic>;

    final ventas = (respuesta['data'] as List)
        .map((v) => Venta.desdeApi(Map<String, dynamic>.from(v as Map)))
        .toList();

    if (pagina == 1) await guardarEnCache(ventas);
    return ventas;
  }

  Future<Venta> detalle(int id) async {
    final respuesta = await _api.obtener('/ventas/$id') as Map<String, dynamic>;
    final venta = Venta.desdeApi(respuesta);
    await guardarEnCache([venta]);
    return venta;
  }

  Future<Map<String, dynamic>> resumen({DateTime? desde, DateTime? hasta}) async {
    final respuesta = await _api.obtener('/ventas-resumen', parametros: {
      'desde': desde == null ? null : _soloFecha(desde),
      'hasta': hasta == null ? null : _soloFecha(hasta),
    }) as Map<String, dynamic>;
    return respuesta;
  }

  Future<void> guardarEnCache(List<Venta> ventas) async {
    if (ventas.isEmpty) return;
    final db = await BaseDatos.instancia;
    final lote = db.batch();
    for (final venta in ventas) {
      lote.insert('ventas', venta.aFila(), conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await lote.commit(noResult: true);
  }

  /// Últimas ventas guardadas: lo que se muestra cuando no hay internet.
  Future<List<Venta>> listarCache({int limite = 50}) async {
    final db = await BaseDatos.instancia;
    final filas = await db.query('ventas', orderBy: 'fecha DESC', limit: limite);
    return filas.map((f) => Venta.desdeFila(f)).toList();
  }

  String _soloFecha(DateTime valor) =>
      '${valor.year.toString().padLeft(4, '0')}-'
      '${valor.month.toString().padLeft(2, '0')}-'
      '${valor.day.toString().padLeft(2, '0')}';
}
