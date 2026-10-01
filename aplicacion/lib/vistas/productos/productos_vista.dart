import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../modelos/producto.dart';
import '../../vistamodelos/productos_vistamodelo.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../../vistamodelos/venta_vistamodelo.dart';
import '../widgets/comunes.dart';
import '../widgets/foto_producto.dart';

/// Catálogo cacheado en el dispositivo, con sincronización manual.
class ProductosVista extends StatefulWidget {
  const ProductosVista({super.key});

  @override
  State<ProductosVista> createState() => _ProductosVistaState();
}

class _ProductosVistaState extends State<ProductosVista> {
  bool _iniciado = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_iniciado) return;
    _iniciado = true;
    if (context.read<SesionVistaModelo>().puedeVerProductos) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) context.read<ProductosVistaModelo>().cargar();
      });
    }
  }

  Future<void> _sincronizar() async {
    final vm = context.read<ProductosVistaModelo>();
    final cantidad = await vm.sincronizar();
    if (!mounted) return;
    if (cantidad == null) {
      mostrarMensaje(context, vm.error ?? 'No se pudo sincronizar', esError: true);
    } else {
      mostrarMensaje(context, '$cantidad productos actualizados');
    }
  }

  /// El aviso ("agregado" o el motivo por el que no se pudo) lo emite el
  /// ViewModel del punto de venta, así no se muestran dos mensajes.
  Future<void> _agregarAlCarrito(Producto producto) =>
      context.read<VentaVistaModelo>().agregar(producto);

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionVistaModelo>();
    if (!sesion.puedeVerProductos) {
      return const EstadoVacio(
        icono: Icons.lock_outline,
        titulo: 'Acceso restringido',
        detalle: 'No tiene el permiso "Ver Productos".',
      );
    }

    final vm = context.watch<ProductosVistaModelo>();

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  onChanged: vm.buscar,
                  style: const TextStyle(fontSize: 14),
                  decoration: const InputDecoration(
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                    hintText: 'Buscar en el catálogo',
                    prefixIcon: Icon(Icons.search, size: 19),
                    prefixIconConstraints: BoxConstraints(minWidth: 38, minHeight: 38),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  onTap: vm.sincronizando ? null : _sincronizar,
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    height: 42,
                    width: 42,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColores.borde),
                    ),
                    child: vm.sincronizando
                        ? const Padding(
                            padding: EdgeInsets.all(11),
                            child: CircularProgressIndicator(
                              strokeWidth: 2.2,
                              color: AppColores.primario,
                            ),
                          )
                        : const Icon(Icons.sync, color: AppColores.primario),
                  ),
                ),
              ),
            ],
          ),
        ),
        if (vm.ultimaSincronizacion != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Text(
              'Catálogo actualizado el ${fechaHora(vm.ultimaSincronizacion)}',
              style: const TextStyle(fontSize: 11.5, color: AppColores.textoSuave),
            ),
          ),
        Expanded(
          child: vm.cargando && vm.productos.isEmpty
              ? const Center(child: CircularProgressIndicator(color: AppColores.primario))
              : vm.productos.isEmpty
              ? EstadoVacio(
                  icono: Icons.inventory_2_outlined,
                  titulo: vm.catalogoVacio ? 'Catálogo vacío' : 'Sin resultados',
                  detalle: vm.catalogoVacio
                      ? 'Sincronice para descargar los productos del servidor.'
                      : 'Pruebe con otro nombre o código.',
                  accion: vm.catalogoVacio
                      ? ElevatedButton.icon(
                          onPressed: _sincronizar,
                          icon: const Icon(Icons.sync),
                          label: const Text('Sincronizar ahora'),
                        )
                      : null,
                )
              : RefreshIndicator(
                  onRefresh: _sincronizar,
                  color: AppColores.primario,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(12, 2, 12, 12),
                    itemCount: vm.productos.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 6),
                    itemBuilder: (context, indice) => _FilaProducto(
                      producto: vm.productos[indice],
                      puedeVender: sesion.puedeVender,
                      alAgregar: () => _agregarAlCarrito(vm.productos[indice]),
                    ),
                  ),
                ),
        ),
      ],
    );
  }
}

class _FilaProducto extends StatelessWidget {
  const _FilaProducto({required this.producto, required this.puedeVender, required this.alAgregar});

  final Producto producto;
  final bool puedeVender;
  final VoidCallback alAgregar;

  @override
  Widget build(BuildContext context) {
    final sinStock = producto.stock <= 0;
    final stockBajo = producto.stock > 0 && producto.stock <= 5;

    return Tarjeta(
      padding: const EdgeInsets.fromLTRB(10, 7, 6, 7),
      hijo: Row(
        children: [
          FotoProducto(foto: producto.foto, tamano: 40, radio: 10),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  producto.nombre,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w600,
                    color: AppColores.texto,
                    height: 1.2,
                  ),
                ),
                Text(
                  '${producto.codigo}${producto.categoria == null ? '' : ' · ${producto.categoria}'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 11, color: AppColores.textoSuave, height: 1.3),
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    Text(
                      dinero(producto.precioVenta),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColores.primario,
                        fontSize: 13,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Etiqueta(
                        texto: sinStock ? 'Sin stock' : '${producto.stock} ${producto.unidad}',
                        color: sinStock
                            ? AppColores.rojo
                            : stockBajo
                            ? AppColores.ambar
                            : AppColores.textoSuave,
                        icono: sinStock || stockBajo ? Icons.warning_amber_rounded : null,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          if (puedeVender)
            IconButton(
              onPressed: sinStock ? null : alAgregar,
              icon: const Icon(Icons.add_shopping_cart, size: 21),
              visualDensity: VisualDensity.compact,
              color: AppColores.primario,
              tooltip: 'Agregar a la venta',
            ),
        ],
      ),
    );
  }
}
