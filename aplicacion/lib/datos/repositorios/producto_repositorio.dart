import 'package:sqflite/sqflite.dart';

import '../../modelos/producto.dart';
import '../servicios/api_servicio.dart';
import '../servicios/base_datos.dart';

/// Catálogo de productos: se descarga de la API y se cachea en SQLite
/// para que la búsqueda del punto de venta funcione al instante y sin datos.
class ProductoRepositorio {
  ProductoRepositorio(this._api);

  final ApiServicio _api;

  static const String _claveSync = 'productos_sincronizados';

  Future<DateTime?> ultimaSincronizacion() async {
    final valor = await BaseDatos.ajuste(_claveSync);
    return valor == null ? null : DateTime.tryParse(valor);
  }

  /// Descarga todo el catálogo (paginado de 500 en 500) y lo reemplaza en local.
  Future<int> sincronizar() async {
    final productos = <Producto>[];
    var pagina = 1;
    var ultimaPagina = 1;

    do {
      final respuesta = await _api.obtener('/productos', parametros: {
        'per_page': 500,
        'page': pagina,
      }) as Map<String, dynamic>;

      productos.addAll(
        (respuesta['data'] as List)
            .map((p) => Producto.desdeApi(Map<String, dynamic>.from(p as Map))),
      );
      ultimaPagina = (respuesta['last_page'] as num?)?.toInt() ?? 1;
      pagina++;
    } while (pagina <= ultimaPagina);

    final db = await BaseDatos.instancia;
    await db.transaction((txn) async {
      await txn.delete('productos');
      final lote = txn.batch();
      for (final producto in productos) {
        lote.insert('productos', producto.aFila(), conflictAlgorithm: ConflictAlgorithm.replace);
      }
      await lote.commit(noResult: true);
    });
    await BaseDatos.guardarAjuste(_claveSync, DateTime.now().toIso8601String());

    return productos.length;
  }

  Future<List<Producto>> buscarLocal(String texto, {int limite = 50}) async {
    final db = await BaseDatos.instancia;
    final consulta = texto.trim();
    final filas = consulta.isEmpty
        ? await db.query('productos', orderBy: 'nombre', limit: limite)
        : await db.query(
            'productos',
            where: 'nombre LIKE ? OR codigo LIKE ? OR codigo_barras LIKE ? OR categoria LIKE ?',
            whereArgs: List.filled(4, '%$consulta%'),
            orderBy: 'nombre',
            limit: limite,
          );
    return filas.map((f) => Producto.desdeFila(f)).toList();
  }

  Future<Producto?> porCodigoBarras(String codigo) async {
    final db = await BaseDatos.instancia;
    final filas = await db.query(
      'productos',
      where: 'codigo_barras = ? OR codigo = ?',
      whereArgs: [codigo.trim(), codigo.trim()],
      limit: 1,
    );
    return filas.isEmpty ? null : Producto.desdeFila(filas.first);
  }

  Future<int> cantidadEnCache() async {
    final db = await BaseDatos.instancia;
    return Sqflite.firstIntValue(await db.rawQuery('SELECT COUNT(*) FROM productos')) ?? 0;
  }

  /// Descuenta el stock cacheado tras una venta para que la vista no mienta
  /// hasta la próxima sincronización.
  Future<void> descontarStock(Map<int, int> cantidades) async {
    if (cantidades.isEmpty) return;
    final db = await BaseDatos.instancia;
    final lote = db.batch();
    cantidades.forEach((productoId, cantidad) {
      lote.rawUpdate(
        'UPDATE productos SET stock = MAX(stock - ?, 0) WHERE id = ?',
        [cantidad, productoId],
      );
    });
    await lote.commit(noResult: true);
  }
}
