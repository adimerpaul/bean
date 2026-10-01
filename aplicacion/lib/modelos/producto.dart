import '../core/formato.dart';

class Producto {
  Producto({
    required this.id,
    required this.codigo,
    this.codigoBarras,
    required this.nombre,
    this.categoria,
    required this.unidad,
    required this.precioCompra,
    required this.precioVenta,
    required this.stock,
    this.foto,
  });

  final int id;
  final String codigo;
  final String? codigoBarras;
  final String nombre;
  final String? categoria;
  final String unidad;
  final double precioCompra;
  final double precioVenta;
  final int stock;
  final String? foto;

  factory Producto.desdeApi(Map<String, dynamic> json) => Producto(
        id: aEntero(json['id']),
        codigo: (json['codigo'] ?? '').toString(),
        codigoBarras: json['codigo_barras']?.toString(),
        nombre: (json['nombre'] ?? '').toString(),
        categoria: json['categoria']?.toString(),
        unidad: (json['unidad'] ?? 'UND').toString(),
        precioCompra: aDouble(json['precio_compra']),
        precioVenta: aDouble(json['precio_venta']),
        stock: aEntero(json['stock_inicial']),
        foto: json['foto']?.toString(),
      );

  factory Producto.desdeFila(Map<String, dynamic> fila) => Producto(
        id: aEntero(fila['id']),
        codigo: (fila['codigo'] ?? '').toString(),
        codigoBarras: fila['codigo_barras']?.toString(),
        nombre: (fila['nombre'] ?? '').toString(),
        categoria: fila['categoria']?.toString(),
        unidad: (fila['unidad'] ?? 'UND').toString(),
        precioCompra: aDouble(fila['precio_compra']),
        precioVenta: aDouble(fila['precio_venta']),
        stock: aEntero(fila['stock']),
        foto: fila['foto']?.toString(),
      );

  Map<String, Object?> aFila() => {
        'id': id,
        'codigo': codigo,
        'codigo_barras': codigoBarras,
        'nombre': nombre,
        'categoria': categoria,
        'unidad': unidad,
        'precio_compra': precioCompra,
        'precio_venta': precioVenta,
        'stock': stock,
        'foto': foto,
      };
}
