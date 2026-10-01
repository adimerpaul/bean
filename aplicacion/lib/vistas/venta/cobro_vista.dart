import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../datos/repositorios/carrito_repositorio.dart';
import '../../datos/repositorios/producto_repositorio.dart';
import '../../datos/repositorios/venta_repositorio.dart';
import '../../modelos/item_carrito.dart';
import '../../vistamodelos/cobro_vistamodelo.dart';
import '../widgets/comunes.dart';
import '../widgets/foto_producto.dart';
import 'venta_registrada_vista.dart';

/// "Revisar orden": confirma el detalle, aplica descuento y cobra.
class CobroVista extends StatelessWidget {
  const CobroVista({super.key, required this.items});

  final List<ItemCarrito> items;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => CobroVistaModelo(
        items,
        context.read<VentaRepositorio>(),
        context.read<CarritoRepositorio>(),
        context.read<ProductoRepositorio>(),
      ),
      child: const _CobroCuerpo(),
    );
  }
}

class _CobroCuerpo extends StatelessWidget {
  const _CobroCuerpo();

  Future<void> _cobrar(BuildContext context) async {
    final vm = context.read<CobroVistaModelo>();
    final navegador = Navigator.of(context);
    final venta = await vm.cobrar();

    if (!context.mounted) return;
    if (venta == null) {
      mostrarMensaje(context, vm.error ?? 'No se pudo registrar la venta', esError: true);
      return;
    }

    await navegador.push<void>(
      MaterialPageRoute<void>(
        builder: (_) => VentaRegistradaVista(venta: venta, cambio: vm.cambio),
      ),
    );
    navegador.pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CobroVistaModelo>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Revisar orden'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context, false),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 20),
        children: [
          Tarjeta(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            hijo: Column(
              children: [
                for (final item in vm.items)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        FotoProducto(foto: item.foto, tamano: 38, radio: 10),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.nombre,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                              Text(
                                '${item.cantidad} ${item.unidad} × ${dinero(item.precioVenta)}',
                                style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          dinero(item.total),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Tarjeta(
            hijo: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Forma de pago', style: TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 10),
                Row(
                  children: [
                    for (final tipo in TipoPago.values) ...[
                      Expanded(
                        child: _OpcionPago(
                          tipo: tipo,
                          seleccionado: vm.tipoPago == tipo,
                          alTocar: () => vm.cambiarTipoPago(tipo),
                        ),
                      ),
                      if (tipo != TipoPago.values.last) const SizedBox(width: 8),
                    ],
                  ],
                ),
                if (vm.tipoPago == TipoPago.combinado) ...[
                  const SizedBox(height: 12),
                  TextField(
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
                    onChanged: vm.cambiarEfectivoCombinado,
                    decoration: InputDecoration(
                      labelText: 'Monto en efectivo',
                      prefixText: '$kMoneda ',
                      helperText: 'El resto se cobra por QR: ${dinero(vm.montoQr)}',
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
                        onChanged: vm.cambiarDescuento,
                        decoration: const InputDecoration(
                          labelText: 'Descuento',
                          prefixText: '$kMoneda ',
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]'))],
                        onChanged: vm.cambiarRecibido,
                        enabled: vm.tipoPago != TipoPago.qr,
                        decoration: const InputDecoration(
                          labelText: 'Paga con',
                          prefixText: '$kMoneda ',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  maxLines: 2,
                  textCapitalization: TextCapitalization.sentences,
                  onChanged: vm.cambiarObservacion,
                  decoration: const InputDecoration(labelText: 'Observación (opcional)'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Tarjeta(
            hijo: Column(
              children: [
                _Linea(etiqueta: 'Subtotal (${vm.cantidadItems} items)', valor: dinero(vm.subtotal)),
                if (vm.descuento > 0)
                  _Linea(
                    etiqueta: 'Descuento',
                    valor: '- ${dinero(vm.descuento)}',
                    color: AppColores.rojo,
                  ),
                const Divider(height: 20),
                _Linea(etiqueta: 'Total a cobrar', valor: dinero(vm.total), destacado: true),
                if (vm.tipoPago == TipoPago.combinado) ...[
                  const SizedBox(height: 6),
                  _Linea(etiqueta: 'Efectivo', valor: dinero(vm.montoEfectivo)),
                  _Linea(etiqueta: 'QR', valor: dinero(vm.montoQr)),
                ],
                if (vm.cambio > 0) ...[
                  const SizedBox(height: 6),
                  _Linea(
                    etiqueta: 'Cambio',
                    valor: dinero(vm.cambio),
                    color: AppColores.ambar,
                    destacado: true,
                  ),
                ],
              ],
            ),
          ),
          if (vm.advertencia != null) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                const Icon(Icons.info_outline, size: 16, color: AppColores.rojo),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    vm.advertencia!,
                    style: const TextStyle(color: AppColores.rojo, fontSize: 12.5),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 18),
          SizedBox(
            height: 54,
            child: ElevatedButton.icon(
              onPressed: vm.puedeCobrar ? () => _cobrar(context) : null,
              icon: vm.cargando
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                    )
                  : const Icon(Icons.check_circle_outline),
              label: Text(vm.cargando ? 'Registrando...' : 'Cobrar ${dinero(vm.total)}'),
              style: ElevatedButton.styleFrom(
                disabledBackgroundColor: AppColores.borde,
                disabledForegroundColor: AppColores.textoSuave,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OpcionPago extends StatelessWidget {
  const _OpcionPago({required this.tipo, required this.seleccionado, required this.alTocar});

  final TipoPago tipo;
  final bool seleccionado;
  final VoidCallback alTocar;

  IconData get _icono => switch (tipo) {
        TipoPago.efectivo => Icons.payments_outlined,
        TipoPago.qr => Icons.qr_code_2,
        TipoPago.combinado => Icons.call_split,
      };

  @override
  Widget build(BuildContext context) {
    return Material(
      color: seleccionado ? AppColores.primarioSuave : Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: alTocar,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: seleccionado ? AppColores.primario : AppColores.borde,
              width: seleccionado ? 1.6 : 1,
            ),
          ),
          child: Column(
            children: [
              Icon(_icono, color: seleccionado ? AppColores.primario : AppColores.textoSuave, size: 22),
              const SizedBox(height: 4),
              Text(
                tipo.etiqueta,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: seleccionado ? AppColores.primario : AppColores.textoSuave,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Linea extends StatelessWidget {
  const _Linea({
    required this.etiqueta,
    required this.valor,
    this.color,
    this.destacado = false,
  });

  final String etiqueta;
  final String valor;
  final Color? color;
  final bool destacado;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            etiqueta,
            style: TextStyle(
              color: destacado ? AppColores.texto : AppColores.textoSuave,
              fontWeight: destacado ? FontWeight.w700 : FontWeight.w500,
            ),
          ),
          Text(
            valor,
            style: TextStyle(
              fontSize: destacado ? 18 : 14,
              fontWeight: destacado ? FontWeight.w800 : FontWeight.w600,
              color: color ?? (destacado ? AppColores.primario : AppColores.texto),
            ),
          ),
        ],
      ),
    );
  }
}

