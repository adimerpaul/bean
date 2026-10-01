import 'package:flutter/material.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../modelos/venta.dart';
import '../widgets/comunes.dart';

/// Comprobante en pantalla luego de registrar la venta.
class VentaRegistradaVista extends StatelessWidget {
  const VentaRegistradaVista({super.key, required this.venta, this.cambio = 0});

  final Venta venta;
  final double cambio;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: true,
      child: Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              const SizedBox(height: 26),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: const BoxDecoration(color: AppColores.primarioSuave, shape: BoxShape.circle),
                child: const Icon(Icons.check_rounded, size: 44, color: AppColores.primario),
              ),
              const SizedBox(height: 14),
              const Text(
                'Venta registrada',
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: AppColores.texto),
              ),
              const SizedBox(height: 4),
              Text(
                '${venta.numero} · ${fechaHora(venta.fecha)}',
                style: const TextStyle(color: AppColores.textoSuave),
              ),
              const SizedBox(height: 18),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  children: [
                    Tarjeta(
                      hijo: Column(
                        children: [
                          for (final detalle in venta.detalles)
                            Padding(
                              padding: const EdgeInsets.symmetric(vertical: 5),
                              child: Row(
                                children: [
                                  Text(
                                    '${detalle.cantidad}×',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: AppColores.primario,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      detalle.nombre,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Text(
                                    dinero(detalle.total),
                                    style: const TextStyle(fontWeight: FontWeight.w600),
                                  ),
                                ],
                              ),
                            ),
                          const Divider(height: 20),
                          _Fila(etiqueta: 'Subtotal', valor: dinero(venta.subtotal)),
                          if (venta.descuento > 0)
                            _Fila(etiqueta: 'Descuento', valor: '- ${dinero(venta.descuento)}'),
                          _Fila(etiqueta: 'Total', valor: dinero(venta.total), destacado: true),
                          const SizedBox(height: 8),
                          _Fila(etiqueta: 'Pago', valor: venta.tipoPago),
                          if (venta.tipoPago == 'COMBINADO') ...[
                            _Fila(etiqueta: 'Efectivo', valor: dinero(venta.montoEfectivo)),
                            _Fila(etiqueta: 'QR', valor: dinero(venta.montoQr)),
                          ],
                          if (cambio > 0)
                            _Fila(
                              etiqueta: 'Cambio a devolver',
                              valor: dinero(cambio),
                              destacado: true,
                              color: AppColores.ambar,
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.add_shopping_cart),
                    label: const Text('Nueva venta'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Fila extends StatelessWidget {
  const _Fila({
    required this.etiqueta,
    required this.valor,
    this.destacado = false,
    this.color,
  });

  final String etiqueta;
  final String valor;
  final bool destacado;
  final Color? color;

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
              fontSize: destacado ? 17 : 14,
              color: color ?? (destacado ? AppColores.primario : AppColores.texto),
            ),
          ),
        ],
      ),
    );
  }
}
