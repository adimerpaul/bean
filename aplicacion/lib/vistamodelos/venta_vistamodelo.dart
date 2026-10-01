import 'dart:async';

import 'package:flutter/foundation.dart';

import '../datos/repositorios/carrito_repositorio.dart';
import '../datos/repositorios/producto_repositorio.dart';
import '../modelos/item_carrito.dart';
import '../modelos/producto.dart';
import 'vista_modelo_base.dart';

/// Aviso puntual para la vista: el texto y si hay que tratarlo como error.
///
/// La vista lo usa para dos cosas: el color del mensaje y cuál de los dos
/// pitidos suena.
class Aviso {
  const Aviso(this.mensaje, {this.esError = false});

  final String mensaje;
  final bool esError;
}

/// ViewModel del punto de venta: carrito en curso y buscador de productos.
class VentaVistaModelo extends VistaModeloBase {
  VentaVistaModelo(this._carritos, this._productos);

  final CarritoRepositorio _carritos;
  final ProductoRepositorio _productos;

  final List<ItemCarrito> _items = [];
  List<Producto> _resultados = [];
  String _busqueda = '';
  bool _escaneando = false;
  Timer? _rebote;

  /// Mensajes puntuales para la vista (producto agregado, sin stock, …).
  ///
  /// Va aparte de `notifyListeners` a propósito: la vista lo escucha con un
  /// listener y muestra el aviso fuera del `build`, nunca durante uno.
  final ValueNotifier<Aviso?> avisos = ValueNotifier<Aviso?>(null);

  List<ItemCarrito> get items => List.unmodifiable(_items);
  List<Producto> get resultados => List.unmodifiable(_resultados);
  String get busqueda => _busqueda;
  bool get escaneando => _escaneando;
  bool get buscando => _busqueda.trim().isNotEmpty;
  bool get vacio => _items.isEmpty;

  int get cantidadItems => _items.fold(0, (suma, item) => suma + item.cantidad);
  int get lineas => _items.length;
  double get subtotal => _items.fold(0.0, (suma, item) => suma + item.total);

  /// Cantidad total comprometida de un producto sumando todas sus líneas.
  int cantidadDeProducto(int productoId, {int? exceptoLinea}) => _items
      .where((item) => item.productoId == productoId && item.id != exceptoLinea)
      .fold(0, (suma, item) => suma + item.cantidad);

  Future<void> iniciar() async {
    await ejecutar(() async {
      _items
        ..clear()
        ..addAll(await _carritos.listar());
      _resultados = await _productos.buscarLocal('');
    });
  }

  void alternarEscaner() {
    _escaneando = !_escaneando;
    notificar();
  }

  void cerrarEscaner() {
    if (!_escaneando) return;
    _escaneando = false;
    notificar();
  }

  /// Búsqueda con rebote para no consultar SQLite en cada tecla.
  void buscar(String texto) {
    _busqueda = texto;
    notificar();
    _rebote?.cancel();
    _rebote = Timer(const Duration(milliseconds: 220), () async {
      _resultados = await _productos.buscarLocal(texto);
      notificar();
    });
  }

  void limpiarBusqueda() {
    _rebote?.cancel();
    _busqueda = '';
    buscar('');
  }

  /// Agrega el producto **como una línea nueva**, aunque ya esté en el carrito.
  /// Devuelve `false` si no hay stock suficiente contando las demás líneas.
  ///
  /// `codigoLeido` llega sólo cuando el producto entró por el escáner; se
  /// antepone al aviso para que el vendedor vea qué código se leyó.
  Future<bool> agregar(Producto producto, {int cantidad = 1, String? codigoLeido}) async {
    final comprometido = cantidadDeProducto(producto.id);
    final prefijo = codigoLeido == null ? '' : '$codigoLeido · ';

    if (producto.stock <= 0) {
      _avisar('$prefijo${producto.nombre} no tiene stock disponible', esError: true);
      return false;
    }
    if (comprometido + cantidad > producto.stock) {
      _avisar(
        '${prefijo}Sólo quedan ${producto.stock - comprometido} ${producto.unidad} '
        'de ${producto.nombre}',
        esError: true,
      );
      return false;
    }

    final item = ItemCarrito.desdeProducto(producto, cantidad: cantidad);
    final id = await _carritos.agregar(item);
    _items.add(item.copiar(id: id));
    notificar();
    _avisar('$prefijo${producto.nombre} agregado');
    return true;
  }

  Future<void> cambiarCantidad(ItemCarrito item, int cantidad) async {
    if (cantidad <= 0) return quitar(item);

    final otras = cantidadDeProducto(item.productoId, exceptoLinea: item.id);
    if (otras + cantidad > item.stock) {
      _avisar(
        'Sólo quedan ${item.stock - otras} ${item.unidad} de ${item.nombre}',
        esError: true,
      );
      return;
    }

    final indice = _items.indexWhere((i) => i.id == item.id);
    if (indice < 0) return;
    _items[indice] = item.copiar(cantidad: cantidad);
    notificar();
    if (item.id != null) await _carritos.actualizarCantidad(item.id!, cantidad);
  }

  Future<void> aumentar(ItemCarrito item) => cambiarCantidad(item, item.cantidad + 1);

  Future<void> disminuir(ItemCarrito item) => cambiarCantidad(item, item.cantidad - 1);

  Future<void> quitar(ItemCarrito item) async {
    _items.removeWhere((i) => i.id == item.id);
    notificar();
    if (item.id != null) await _carritos.quitar(item.id!);
  }

  Future<void> limpiar() async {
    _items.clear();
    notificar();
    await _carritos.limpiar();
  }

  /// Agrega el producto leído por el escáner. Devuelve `true` si entró al
  /// carrito; `false` si el código no existe o no hay stock.
  Future<bool> agregarPorCodigo(String codigo) async {
    final producto = await _productos.porCodigoBarras(codigo);
    if (producto == null) {
      _avisar('Código $codigo no registrado', esError: true);
      return false;
    }
    return agregar(producto, codigoLeido: codigo);
  }

  /// Tras registrar una venta el carrito ya se vació en la base local.
  Future<void> recargarDespuesDeVenta() async {
    _items
      ..clear()
      ..addAll(await _carritos.listar());
    _resultados = await _productos.buscarLocal(_busqueda);
    notificar();
  }

  void _avisar(String mensaje, {bool esError = false}) {
    // El null intermedio hace que el aviso se emita aunque se repita el texto
    // (agregar dos veces el mismo producto, por ejemplo).
    avisos.value = null;
    avisos.value = Aviso(mensaje, esError: esError);
  }

  @override
  void dispose() {
    _rebote?.cancel();
    avisos.dispose();
    super.dispose();
  }
}
