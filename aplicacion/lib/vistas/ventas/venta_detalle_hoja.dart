import 'package:flutter/material.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../modelos/venta.dart';
import '../widgets/comunes.dart';
import '../widgets/foto_producto.dart';

/// Detalle de una venta mostrado como hoja inferior.
class VentaDetalleHoja extends StatelessWidget {
  const VentaDetalleHoja({super.key, required this.venta});

  final Venta venta;

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.72,
      minChildSize: 0.45,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, desplazamiento) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 10),
            Container(
              height: 4,
              width: 44,
              decoration: BoxDecoration(
                color: AppColores.borde,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 10),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          venta.numero,
                          style: const TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            color: AppColores.texto,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${fechaHora(venta.fecha)} · ${venta.usuarioNombre}',
                          style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
                        ),
                      ],
                    ),
                  ),
                  Etiqueta(
                    texto: venta.estado,
                    color: venta.anulada ? AppColores.rojo : AppColores.primario,
                    icono: venta.anulada ? Icons.block : Icons.check_circle,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                controller: desplazamiento,
                padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                children: [
                  for (final detalle in venta.detalles)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 7),
                      child: Row(
                        children: [
                          FotoProducto(foto: detalle.foto, tamano: 40, radio: 10),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  detalle.nombre,
                                  style: const TextStyle(fontWeight: FontWeight.w600),
                                ),
                                Text(
                                  '${detalle.cantidad} ${detalle.unidad}'
                                  '${detalle.precioVenta > 0 ? ' × ${dinero(detalle.precioVenta)}' : ''}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: AppColores.textoSuave,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (detalle.total > 0)
                            Text(
                              dinero(detalle.total),
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                        ],
                      ),
                    ),
                  const Divider(height: 24),
                  _Fila(etiqueta: 'Subtotal', valor: dinero(venta.subtotal)),
                  if (venta.descuento > 0)
                    _Fila(etiqueta: 'Descuento', valor: '- ${dinero(venta.descuento)}'),
                  _Fila(etiqueta: 'Total', valor: dinero(venta.total), destacado: true),
                  const SizedBox(height: 10),
                  _Fila(etiqueta: 'Forma de pago', valor: venta.tipoPago),
                  if (venta.montoEfectivo > 0)
                    _Fila(etiqueta: 'Efectivo', valor: dinero(venta.montoEfectivo)),
                  if (venta.montoQr > 0) _Fila(etiqueta: 'QR', valor: dinero(venta.montoQr)),
                  if ((venta.observacion ?? '').isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Text(
                      'Observación: ${venta.observacion}',
                      style: const TextStyle(fontSize: 12.5, color: AppColores.textoSuave),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Fila extends StatelessWidget {
  const _Fila({required this.etiqueta, required this.valor, this.destacado = false});

  final String etiqueta;
  final String valor;
  final bool destacado;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(etiqueta, style: const TextStyle(color: AppColores.textoSuave)),
          Text(
            valor,
            style: TextStyle(
              fontWeight: destacado ? FontWeight.w800 : FontWeight.w600,
              fontSize: destacado ? 18 : 14,
              color: destacado ? AppColores.primario : AppColores.texto,
            ),
          ),
        ],
      ),
    );
  }
}
