import '../../modelos/item_carrito.dart';
import '../servicios/base_datos.dart';

/// El carrito vive en SQLite: si se cierra la app, la venta en curso no se
/// pierde. Cada agregado es una línea propia, aunque se repita el producto.
class CarritoRepositorio {
  Future<List<ItemCarrito>> listar() async {
    final db = await BaseDatos.instancia;
    final filas = await db.query('carrito', orderBy: 'id');
    return filas.map((f) => ItemCarrito.desdeFila(f)).toList();
  }

  /// Inserta la línea y devuelve el `id` que le asignó SQLite.
  Future<int> agregar(ItemCarrito item) async {
    final db = await BaseDatos.instancia;
    return db.insert('carrito', item.aFila());
  }

  Future<void> actualizarCantidad(int id, int cantidad) async {
    final db = await BaseDatos.instancia;
    await db.update('carrito', {'cantidad': cantidad}, where: 'id = ?', whereArgs: [id]);
  }

  Future<void> quitar(int id) async {
    final db = await BaseDatos.instancia;
    await db.delete('carrito', where: 'id = ?', whereArgs: [id]);
  }

  Future<void> limpiar() async {
    final db = await BaseDatos.instancia;
    await db.delete('carrito');
  }
}
