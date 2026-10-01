import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/tema.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../../vistamodelos/venta_vistamodelo.dart';
import '../cuenta/cuenta_vista.dart';
import '../productos/productos_vista.dart';
import '../venta/venta_vista.dart';
import '../ventas/ventas_vista.dart';

/// Estructura principal: contenido + menú inferior con el botón circular
/// de "Nueva venta" en el centro.
class PrincipalVista extends StatefulWidget {
  const PrincipalVista({super.key});

  @override
  State<PrincipalVista> createState() => _PrincipalVistaState();
}

class _PrincipalVistaState extends State<PrincipalVista> {
  int _pestana = 0;

  static const _titulos = ['Nueva venta', 'Ventas', 'Productos', 'Mi cuenta'];

  void _cambiar(int indice) {
    if (_pestana == indice) return;
    context.read<VentaVistaModelo>().cerrarEscaner();
    setState(() => _pestana = indice);
  }

  /// El círculo central: lleva al punto de venta y abre el escáner.
  void _nuevaVenta() {
    final venta = context.read<VentaVistaModelo>();
    if (_pestana != 0) {
      setState(() => _pestana = 0);
      return;
    }
    venta.alternarEscaner();
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionVistaModelo>();
    final carrito = context.watch<VentaVistaModelo>();

    return Scaffold(
      appBar: AppBar(
        title: Text(_titulos[_pestana]),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 14),
            child: Center(
              child: Text(
                sesion.usuario?.nombre ?? '',
                style: const TextStyle(fontSize: 12.5, color: AppColores.textoSuave),
              ),
            ),
          ),
        ],
      ),
      body: IndexedStack(
        index: _pestana,
        children: const [
          VentaVista(),
          VentasVista(),
          ProductosVista(),
          CuentaVista(),
        ],
      ),
      floatingActionButton: _BotonCentral(
        cantidad: carrito.cantidadItems,
        escaneando: carrito.escaneando && _pestana == 0,
        alTocar: _nuevaVenta,
      ),
      floatingActionButtonLocation: const _CentroBajo(10),
      bottomNavigationBar: _MenuInferior(
        pestana: _pestana,
        alCambiar: _cambiar,
      ),
    );
  }
}

/// `centerDocked` bajado unos píxeles para que el círculo sobresalga menos y
/// no se monte sobre el botón "Revisar Orden" del punto de venta.
class _CentroBajo extends FloatingActionButtonLocation {
  const _CentroBajo(this.desplazamiento);

  final double desplazamiento;

  @override
  Offset getOffset(ScaffoldPrelayoutGeometry geometria) {
    final base = FloatingActionButtonLocation.centerDocked.getOffset(geometria);
    return Offset(base.dx, base.dy + desplazamiento);
  }
}

class _BotonCentral extends StatelessWidget {
  const _BotonCentral({
    required this.cantidad,
    required this.escaneando,
    required this.alTocar,
  });

  final int cantidad;
  final bool escaneando;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 64,
      width: 64,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Material(
            color: AppColores.primario,
            shape: const CircleBorder(),
            elevation: 4,
            shadowColor: AppColores.primario.withValues(alpha: 0.5),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: alTocar,
              child: Center(
                child: Icon(
                  escaneando ? Icons.close : Icons.qr_code_scanner,
                  color: Colors.white,
                  size: 28,
                ),
              ),
            ),
          ),
          if (cantidad > 0)
            Positioned(
              right: -2,
              top: -2,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                decoration: BoxDecoration(
                  color: AppColores.ambar,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: Text(
                  '$cantidad',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _MenuInferior extends StatelessWidget {
  const _MenuInferior({required this.pestana, required this.alCambiar});

  final int pestana;
  final ValueChanged<int> alCambiar;

  @override
  Widget build(BuildContext context) {
    return BottomAppBar(
      color: Colors.white,
      elevation: 12,
      shape: const CircularNotchedRectangle(),
      notchMargin: 9,
      height: 66,
      padding: EdgeInsets.zero,
      child: Row(
        children: [
          _Opcion(
            icono: Icons.point_of_sale_outlined,
            iconoActivo: Icons.point_of_sale,
            etiqueta: 'Vender',
            activo: pestana == 0,
            alTocar: () => alCambiar(0),
          ),
          _Opcion(
            icono: Icons.receipt_long_outlined,
            iconoActivo: Icons.receipt_long,
            etiqueta: 'Ventas',
            activo: pestana == 1,
            alTocar: () => alCambiar(1),
          ),
          const SizedBox(width: 64),
          _Opcion(
            icono: Icons.inventory_2_outlined,
            iconoActivo: Icons.inventory_2,
            etiqueta: 'Productos',
            activo: pestana == 2,
            alTocar: () => alCambiar(2),
          ),
          _Opcion(
            icono: Icons.person_outline,
            iconoActivo: Icons.person,
            etiqueta: 'Cuenta',
            activo: pestana == 3,
            alTocar: () => alCambiar(3),
          ),
        ],
      ),
    );
  }
}

class _Opcion extends StatelessWidget {
  const _Opcion({
    required this.icono,
    required this.iconoActivo,
    required this.etiqueta,
    required this.activo,
    required this.alTocar,
  });

  final IconData icono;
  final IconData iconoActivo;
  final String etiqueta;
  final bool activo;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final color = activo ? AppColores.primario : AppColores.textoSuave;

    return Expanded(
      child: InkResponse(
        onTap: alTocar,
        radius: 42,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(activo ? iconoActivo : icono, color: color, size: 23),
            const SizedBox(height: 3),
            Text(
              etiqueta,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: activo ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
