import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../modelos/venta.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../../vistamodelos/ventas_vistamodelo.dart';
import '../widgets/comunes.dart';
import 'venta_detalle_hoja.dart';

/// Listado de ventas con filtro por fecha, total del período y detalle.
class VentasVista extends StatefulWidget {
  const VentasVista({super.key});

  @override
  State<VentasVista> createState() => _VentasVistaState();
}

class _VentasVistaState extends State<VentasVista> {
  final _desplazamiento = ScrollController();
  bool _iniciado = false;

  @override
  void initState() {
    super.initState();
    _desplazamiento.addListener(() {
      if (_desplazamiento.position.pixels >=
          _desplazamiento.position.maxScrollExtent - 240) {
        context.read<VentasVistaModelo>().cargarMas();
      }
    });
  }

  @override
  void dispose() {
    _desplazamiento.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_iniciado) return;
    _iniciado = true;
    if (context.read<SesionVistaModelo>().puedeVerVentas) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) context.read<VentasVistaModelo>().cargar();
      });
    }
  }

  Future<void> _abrirDetalle(Venta venta) async {
    final vm = context.read<VentasVistaModelo>();
    final completa = await vm.detalle(venta.id) ?? venta;
    if (!mounted) return;

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => VentaDetalleHoja(venta: completa),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionVistaModelo>();
    if (!sesion.puedeVerVentas) {
      return const EstadoVacio(
        icono: Icons.lock_outline,
        titulo: 'Acceso restringido',
        detalle: 'No tiene el permiso "Ver Ventas".',
      );
    }

    final vm = context.watch<VentasVistaModelo>();

    return Column(
      children: [
        _Filtros(
          rango: vm.rango,
          alCambiar: vm.cambiarRango,
          alBuscar: vm.cambiarBusqueda,
        ),
        _Resumen(
          etiqueta: vm.etiquetaRango,
          total: vm.totalListado,
          cantidad: vm.cantidadValidas,
          desdeCache: vm.desdeCache,
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: vm.cargar,
            color: AppColores.primario,
            child: vm.cargando && vm.ventas.isEmpty
                ? const Center(child: CircularProgressIndicator(color: AppColores.primario))
                : vm.ventas.isEmpty
                    ? ListView(
                        children: const [
                          SizedBox(height: 60),
                          EstadoVacio(
                            icono: Icons.receipt_long_outlined,
                            titulo: 'Sin ventas en este período',
                            detalle: 'Cambie el filtro de fechas o registre una nueva venta.',
                          ),
                        ],
                      )
                    : ListView.separated(
                        controller: _desplazamiento,
                        padding: const EdgeInsets.fromLTRB(14, 8, 14, 16),
                        itemCount: vm.ventas.length + (vm.cargandoMas ? 1 : 0),
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (context, indice) {
                          if (indice >= vm.ventas.length) {
                            return const Padding(
                              padding: EdgeInsets.all(14),
                              child: Center(
                                child: CircularProgressIndicator(color: AppColores.primario),
                              ),
                            );
                          }
                          final venta = vm.ventas[indice];
                          return _FilaVenta(venta: venta, alTocar: () => _abrirDetalle(venta));
                        },
                      ),
          ),
        ),
      ],
    );
  }
}

class _Filtros extends StatelessWidget {
  const _Filtros({required this.rango, required this.alCambiar, required this.alBuscar});

  final RangoFecha rango;
  final ValueChanged<RangoFecha> alCambiar;
  final ValueChanged<String> alBuscar;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 6),
      child: Column(
        children: [
          TextField(
            onSubmitted: alBuscar,
            textInputAction: TextInputAction.search,
            decoration: const InputDecoration(
              hintText: 'Buscar por número o vendedor',
              prefixIcon: Icon(Icons.search, size: 21),
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 34,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: RangoFecha.values.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (context, indice) {
                final opcion = RangoFecha.values[indice];
                final activo = opcion == rango;
                return ChoiceChip(
                  label: Text(opcion.etiqueta),
                  selected: activo,
                  showCheckmark: false,
                  onSelected: (_) => alCambiar(opcion),
                  selectedColor: AppColores.primario,
                  backgroundColor: Colors.white,
                  labelStyle: TextStyle(
                    color: activo ? Colors.white : AppColores.textoSuave,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                  side: BorderSide(color: activo ? AppColores.primario : AppColores.borde),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _Resumen extends StatelessWidget {
  const _Resumen({
    required this.etiqueta,
    required this.total,
    required this.cantidad,
    required this.desdeCache,
  });

  final String etiqueta;
  final double total;
  final int cantidad;
  final bool desdeCache;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(14, 6, 14, 6),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColores.primario,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  etiqueta,
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  dinero(total),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '$cantidad ${cantidad == 1 ? 'venta' : 'ventas'}',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
              ),
              if (desdeCache) ...[
                const SizedBox(height: 4),
                const Text(
                  'sin conexión',
                  style: TextStyle(color: Colors.white70, fontSize: 11),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _FilaVenta extends StatelessWidget {
  const _FilaVenta({required this.venta, required this.alTocar});

  final Venta venta;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final anulada = venta.anulada;

    return Tarjeta(
      alTocar: alTocar,
      hijo: Row(
        children: [
          Container(
            height: 42,
            width: 42,
            decoration: BoxDecoration(
              color: anulada ? AppColores.rojo.withValues(alpha: 0.1) : AppColores.primarioSuave,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              anulada ? Icons.block : Icons.receipt_long,
              color: anulada ? AppColores.rojo : AppColores.primario,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      venta.numero,
                      style: const TextStyle(fontWeight: FontWeight.w700, color: AppColores.texto),
                    ),
                    const SizedBox(width: 8),
                    Etiqueta(
                      texto: venta.tipoPago,
                      color: venta.tipoPago == 'QR'
                          ? AppColores.azul
                          : venta.tipoPago == 'EFECTIVO'
                          ? AppColores.verde
                          : AppColores.primario,
                    ),
                    if (anulada) ...[
                      const SizedBox(width: 6),
                      const Etiqueta(texto: 'ANULADA', color: AppColores.rojo),
                    ],
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  '${fechaHora(venta.fecha)} · ${venta.cantidadItems} items',
                  style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
                ),
              ],
            ),
          ),
          Text(
            dinero(venta.total),
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: anulada ? AppColores.textoSuave : AppColores.texto,
              decoration: anulada ? TextDecoration.lineThrough : null,
            ),
          ),
        ],
      ),
    );
  }
}
