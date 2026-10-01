import 'package:bean/datos/repositorios/carrito_repositorio.dart';
import 'package:bean/datos/repositorios/producto_repositorio.dart';
import 'package:bean/datos/servicios/api_servicio.dart';
import 'package:bean/modelos/item_carrito.dart';
import 'package:bean/modelos/producto.dart';
import 'package:bean/vistamodelos/venta_vistamodelo.dart';
import 'package:flutter_test/flutter_test.dart';

/// Carrito en memoria: reemplaza a SQLite para poder probar el ViewModel.
class CarritoFalso implements CarritoRepositorio {
  final List<ItemCarrito> lineas = [];
  int _siguienteId = 0;

  @override
  Future<int> agregar(ItemCarrito item) async {
    final id = ++_siguienteId;
    lineas.add(item.copiar(id: id));
    return id;
  }

  @override
  Future<void> actualizarCantidad(int id, int cantidad) async {
    final indice = lineas.indexWhere((l) => l.id == id);
    if (indice >= 0) lineas[indice] = lineas[indice].copiar(cantidad: cantidad);
  }

  @override
  Future<void> quitar(int id) async => lineas.removeWhere((l) => l.id == id);

  @override
  Future<void> limpiar() async => lineas.clear();

  @override
  Future<List<ItemCarrito>> listar() async => List.of(lineas);
}

class ProductosFalso extends ProductoRepositorio {
  ProductosFalso({this.porCodigo}) : super(ApiServicio(urlBase: 'http://localhost/api'));

  /// Producto que devuelve el escáner; `null` simula un código desconocido.
  final Producto? porCodigo;

  @override
  Future<List<Producto>> buscarLocal(String texto, {int limite = 50}) async => const [];

  @override
  Future<Producto?> porCodigoBarras(String codigo) async => porCodigo;
}

Producto crearProducto({int stock = 10}) => Producto(
  id: 1,
  codigo: 'P-1',
  nombre: 'LECHE PIL 1L',
  unidad: 'UND',
  precioCompra: 5,
  precioVenta: 8,
  stock: stock,
);

void main() {
  late CarritoFalso carritos;
  late VentaVistaModelo vm;

  setUp(() {
    carritos = CarritoFalso();
    vm = VentaVistaModelo(carritos, ProductosFalso());
  });

  test('agregar dos veces el mismo producto crea dos líneas', () async {
    final producto = crearProducto();

    expect(await vm.agregar(producto), isTrue);
    expect(await vm.agregar(producto), isTrue);

    expect(vm.lineas, 2);
    expect(vm.cantidadItems, 2);
    expect(vm.items.map((i) => i.id).toSet().length, 2, reason: 'cada línea tiene su id');
    expect(carritos.lineas.length, 2, reason: 'se guardan las dos líneas en la base local');
  });

  test('el stock se controla sumando todas las líneas del producto', () async {
    final producto = crearProducto(stock: 3);

    expect(await vm.agregar(producto, cantidad: 2), isTrue);
    expect(await vm.agregar(producto, cantidad: 2), isFalse, reason: 'sólo queda 1');
    expect(await vm.agregar(producto), isTrue, reason: 'el último sí entra');

    expect(vm.cantidadItems, 3);
    expect(vm.lineas, 2);
  });

  test('cambiar la cantidad de una línea considera a las otras', () async {
    final producto = crearProducto(stock: 5);
    await vm.agregar(producto, cantidad: 2);
    await vm.agregar(producto, cantidad: 2);

    await vm.cambiarCantidad(vm.items.first, 4);
    expect(vm.items.first.cantidad, 2, reason: 'excede el stock, no cambia');

    await vm.cambiarCantidad(vm.items.first, 3);
    expect(vm.items.first.cantidad, 3);
    expect(vm.cantidadItems, 5);
  });

  test('cantidad cero quita sólo esa línea', () async {
    final producto = crearProducto();
    await vm.agregar(producto);
    await vm.agregar(producto, cantidad: 4);

    await vm.cambiarCantidad(vm.items.first, 0);

    expect(vm.lineas, 1);
    expect(vm.items.single.cantidad, 4);
    expect(carritos.lineas.length, 1);
  });

  test('avisa al agregar y cuando no hay stock', () async {
    final avisos = <Aviso>[];
    vm.avisos.addListener(() {
      final aviso = vm.avisos.value;
      if (aviso != null) avisos.add(aviso);
    });

    await vm.agregar(crearProducto(stock: 1));
    await vm.agregar(crearProducto(stock: 1));

    expect(avisos.first.mensaje, contains('agregado'));
    expect(avisos.first.esError, isFalse, reason: 'suena el pitido de éxito');
    expect(avisos.last.mensaje, contains('Sólo quedan 0'));
    expect(avisos.last.esError, isTrue, reason: 'suena el pitido de error');
  });

  group('escáner', () {
    test('el aviso lleva el código leído adelante del producto', () async {
      final vm = VentaVistaModelo(carritos, ProductosFalso(porCodigo: crearProducto()));
      final avisos = <Aviso>[];
      vm.avisos.addListener(() {
        final aviso = vm.avisos.value;
        if (aviso != null) avisos.add(aviso);
      });

      expect(await vm.agregarPorCodigo('7501234567890'), isTrue);

      expect(avisos.single.mensaje, '7501234567890 · LECHE PIL 1L agregado');
      expect(avisos.single.esError, isFalse);
    });

    test('un código desconocido avisa como error y no toca el carrito', () async {
      final vm = VentaVistaModelo(carritos, ProductosFalso());
      final avisos = <Aviso>[];
      vm.avisos.addListener(() {
        final aviso = vm.avisos.value;
        if (aviso != null) avisos.add(aviso);
      });

      expect(await vm.agregarPorCodigo('0000000000000'), isFalse);

      expect(avisos.single.mensaje, contains('no registrado'));
      expect(avisos.single.esError, isTrue);
      expect(vm.vacio, isTrue);
    });
  });
}
