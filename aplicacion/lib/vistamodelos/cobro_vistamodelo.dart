import '../datos/repositorios/carrito_repositorio.dart';
import '../datos/repositorios/producto_repositorio.dart';
import '../datos/repositorios/venta_repositorio.dart';
import '../modelos/item_carrito.dart';
import '../modelos/venta.dart';
import 'vista_modelo_base.dart';

enum TipoPago {
  efectivo('EFECTIVO', 'Efectivo'),
  qr('QR', 'QR'),
  combinado('COMBINADO', 'Combinado');

  const TipoPago(this.valor, this.etiqueta);

  final String valor;
  final String etiqueta;
}

/// ViewModel de la pantalla "Revisar orden": descuento, forma de pago y cobro.
class CobroVistaModelo extends VistaModeloBase {
  CobroVistaModelo(this._items, this._ventas, this._carritos, this._productos);

  final List<ItemCarrito> _items;
  final VentaRepositorio _ventas;
  final CarritoRepositorio _carritos;
  final ProductoRepositorio _productos;

  TipoPago _tipoPago = TipoPago.efectivo;
  double _descuento = 0;
  double _efectivoCombinado = 0;
  double _recibido = 0;
  String _observacion = '';

  List<ItemCarrito> get items => List.unmodifiable(_items);
  TipoPago get tipoPago => _tipoPago;
  double get descuento => _descuento;
  double get recibido => _recibido;
  String get observacion => _observacion;

  double get subtotal => _items.fold(0.0, (suma, item) => suma + item.total);
  double get total => _redondear(subtotal - _descuento);
  int get cantidadItems => _items.fold(0, (suma, item) => suma + item.cantidad);

  double get montoEfectivo => switch (_tipoPago) {
        TipoPago.efectivo => total,
        TipoPago.qr => 0,
        TipoPago.combinado => _redondear(_efectivoCombinado.clamp(0, total).toDouble()),
      };

  double get montoQr => _redondear(total - montoEfectivo);

  /// Cambio a devolver cuando el cliente entrega un billete mayor.
  double get cambio {
    if (_tipoPago == TipoPago.qr) return 0;
    final diferencia = _recibido - montoEfectivo;
    return diferencia > 0 ? _redondear(diferencia) : 0;
  }

  String? get advertencia {
    if (_items.isEmpty) return 'El carrito está vacío';
    if (_descuento > subtotal) return 'El descuento no puede superar el subtotal';
    if (total <= 0) return 'El total debe ser mayor a cero';
    if (_tipoPago == TipoPago.combinado && montoEfectivo <= 0) {
      return 'Indique cuánto paga en efectivo';
    }
    return null;
  }

  bool get puedeCobrar => advertencia == null && !cargando;

  void cambiarTipoPago(TipoPago tipo) {
    _tipoPago = tipo;
    if (tipo == TipoPago.combinado && _efectivoCombinado <= 0) {
      _efectivoCombinado = 0;
    }
    notificar();
  }

  void cambiarDescuento(String texto) {
    _descuento = _aNumero(texto);
    notificar();
  }

  void cambiarEfectivoCombinado(String texto) {
    _efectivoCombinado = _aNumero(texto);
    notificar();
  }

  void cambiarRecibido(String texto) {
    _recibido = _aNumero(texto);
    notificar();
  }

  void cambiarObservacion(String texto) {
    _observacion = texto;
  }

  /// Envía la venta a la API, vacía el carrito y ajusta el stock cacheado.
  Future<Venta?> cobrar() async {
    if (advertencia != null) {
      establecerError(advertencia);
      return null;
    }

    return ejecutar(() async {
      final venta = await _ventas.registrar(
        items: _items,
        descuento: _redondear(_descuento),
        tipoPago: _tipoPago.valor,
        montoEfectivo: montoEfectivo,
        montoQr: montoQr,
        observacion: _observacion.trim(),
      );

      await _carritos.limpiar();

      // Varias líneas pueden ser del mismo producto: se suman antes de
      // descontar el stock cacheado.
      final descuentos = <int, int>{};
      for (final item in _items) {
        descuentos[item.productoId] = (descuentos[item.productoId] ?? 0) + item.cantidad;
      }
      await _productos.descontarStock(descuentos);

      return venta;
    });
  }

  double _redondear(double valor) => (valor * 100).roundToDouble() / 100;

  double _aNumero(String texto) {
    final limpio = texto.trim().replaceAll(',', '.');
    return double.tryParse(limpio) ?? 0;
  }
}
