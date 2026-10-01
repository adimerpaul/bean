import 'dart:convert';

import '../core/formato.dart';

class DetalleVenta {
  DetalleVenta({
    required this.nombre,
    required this.cantidad,
    required this.unidad,
    this.precioVenta = 0,
    this.total = 0,
    this.foto,
  });

  final String nombre;
  final int cantidad;
  final String unidad;
  final double precioVenta;
  final double total;
  final String? foto;

  factory DetalleVenta.desdeJson(Map<String, dynamic> json) => DetalleVenta(
        nombre: (json['nombre'] ?? '').toString(),
        cantidad: aEntero(json['cantidad']),
        unidad: (json['unidad'] ?? '').toString(),
        precioVenta: aDouble(json['precio_venta']),
        total: aDouble(json['total']),
        foto: json['foto']?.toString(),
      );

  Map<String, Object?> aJson() => {
        'nombre': nombre,
        'cantidad': cantidad,
        'unidad': unidad,
        'precio_venta': precioVenta,
        'total': total,
        'foto': foto,
      };
}

class Venta {
  Venta({
    required this.id,
    required this.numero,
    required this.fecha,
    required this.subtotal,
    required this.descuento,
    required this.total,
    required this.tipoPago,
    required this.montoEfectivo,
    required this.montoQr,
    required this.estado,
    required this.usuarioNombre,
    required this.detalles,
    this.observacion,
  });

  final int id;
  final String numero;
  final DateTime? fecha;
  final double subtotal;
  final double descuento;
  final double total;
  final String tipoPago;
  final double montoEfectivo;
  final double montoQr;
  final String estado;
  final String usuarioNombre;
  final List<DetalleVenta> detalles;
  final String? observacion;

  bool get anulada => estado == 'ANULADA';

  int get cantidadItems => detalles.fold(0, (suma, d) => suma + d.cantidad);

  factory Venta.desdeApi(Map<String, dynamic> json) => Venta(
        id: aEntero(json['id']),
        numero: (json['numero'] ?? '').toString(),
        fecha: DateTime.tryParse((json['fecha'] ?? '').toString()),
        subtotal: aDouble(json['subtotal']),
        descuento: aDouble(json['descuento']),
        total: aDouble(json['total']),
        tipoPago: (json['tipo_pago'] ?? '').toString(),
        montoEfectivo: aDouble(json['monto_efectivo']),
        montoQr: aDouble(json['monto_qr']),
        estado: (json['estado'] ?? 'COMPLETADA').toString(),
        usuarioNombre: (json['usuario_nombre'] ?? '').toString(),
        observacion: json['observacion']?.toString(),
        detalles: ((json['detalles'] ?? const []) as List)
            .map((d) => DetalleVenta.desdeJson(Map<String, dynamic>.from(d as Map)))
            .toList(),
      );

  factory Venta.desdeFila(Map<String, dynamic> fila) => Venta(
        id: aEntero(fila['id']),
        numero: (fila['numero'] ?? '').toString(),
        fecha: DateTime.tryParse((fila['fecha'] ?? '').toString()),
        subtotal: aDouble(fila['subtotal']),
        descuento: aDouble(fila['descuento']),
        total: aDouble(fila['total']),
        tipoPago: (fila['tipo_pago'] ?? '').toString(),
        montoEfectivo: aDouble(fila['monto_efectivo']),
        montoQr: aDouble(fila['monto_qr']),
        estado: (fila['estado'] ?? 'COMPLETADA').toString(),
        usuarioNombre: (fila['usuario_nombre'] ?? '').toString(),
        detalles: (jsonDecode((fila['detalles'] ?? '[]').toString()) as List)
            .map((d) => DetalleVenta.desdeJson(Map<String, dynamic>.from(d as Map)))
            .toList(),
      );

  Map<String, Object?> aFila() => {
        'id': id,
        'numero': numero,
        'fecha': fecha?.toIso8601String(),
        'subtotal': subtotal,
        'descuento': descuento,
        'total': total,
        'tipo_pago': tipoPago,
        'monto_efectivo': montoEfectivo,
        'monto_qr': montoQr,
        'estado': estado,
        'usuario_nombre': usuarioNombre,
        'detalles': jsonEncode(detalles.map((d) => d.aJson()).toList()),
      };
}
