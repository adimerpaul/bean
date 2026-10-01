import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../datos/servicios/sonido_servicio.dart';
import '../../modelos/item_carrito.dart';
import '../../modelos/producto.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../../vistamodelos/venta_vistamodelo.dart';
import '../../vistamodelos/ventas_vistamodelo.dart';
import '../widgets/comunes.dart';
import '../widgets/foto_producto.dart';
import 'cobro_vista.dart';
import 'escaner_camara.dart';

/// Punto de venta: buscador + escáner arriba, carrito abajo.
class VentaVista extends StatefulWidget {
  const VentaVista({super.key});

  @override
  State<VentaVista> createState() => _VentaVistaState();
}

class _VentaVistaState extends State<VentaVista> {
  final _busqueda = TextEditingController();
  ValueNotifier<Aviso?>? _avisos;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Los avisos se escuchan aparte del estado: así el mensaje se muestra
    // después de la acción y nunca en medio de un build.
    final avisos = context.read<VentaVistaModelo>().avisos;
    if (identical(avisos, _avisos)) return;
    _avisos?.removeListener(_mostrarAviso);
    _avisos = avisos..addListener(_mostrarAviso);
  }

  @override
  void dispose() {
    _avisos?.removeListener(_mostrarAviso);
    _busqueda.dispose();
    super.dispose();
  }

  void _mostrarAviso() {
    final aviso = _avisos?.value;
    if (aviso == null || !mounted) return;
    // El pitido va primero: en el mostrador se escucha antes de que el
    // vendedor llegue a mirar la pantalla.
    final sonidos = context.read<SonidoServicio>();
    if (aviso.esError) {
      sonidos.error();
    } else {
      sonidos.exito();
    }
    mostrarMensaje(context, aviso.mensaje, esError: aviso.esError);
  }

  Future<void> _revisarOrden() async {
    final vm = context.read<VentaVistaModelo>();
    if (vm.vacio) {
      mostrarMensaje(context, 'Agregue productos antes de cobrar', esError: true);
      return;
    }
    vm.cerrarEscaner();

    final registrada = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => CobroVista(items: vm.items)));

    if (registrada == true) {
      await vm.recargarDespuesDeVenta();
      _busqueda.clear();
      vm.limpiarBusqueda();
      // El listado de ventas debe reflejar la venta recién registrada.
      if (mounted) await context.read<VentasVistaModelo>().cargar();
    }
  }

  /// Teclado para escribir la cantidad en lugar de tocar «+» muchas veces.
  Future<void> _editarCantidad(ItemCarrito item) async {
    final vm = context.read<VentaVistaModelo>();
    // Lo disponible descuenta lo que ya tomaron las otras líneas del mismo producto.
    final disponible = item.stock - vm.cantidadDeProducto(item.productoId, exceptoLinea: item.id);

    final cantidad = await showDialog<int>(
      context: context,
      builder: (_) => _DialogoCantidad(item: item, disponible: disponible),
    );

    // Dejar el campo vacío equivale a cancelar: no se toca la línea.
    if (cantidad != null) await vm.cambiarCantidad(item, cantidad);
  }

  Future<void> _confirmarLimpiar() async {
    final vm = context.read<VentaVistaModelo>();
    if (vm.vacio) return;

    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Limpiar carrito'),
        content: const Text('¿Quitar todos los productos de esta venta?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto, false), child: const Text('No')),
          TextButton(
            onPressed: () => Navigator.pop(contexto, true),
            child: const Text('Sí, limpiar'),
          ),
        ],
      ),
    );

    if (confirmado == true) await vm.limpiar();
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<VentaVistaModelo>();
    final sesion = context.watch<SesionVistaModelo>();

    if (!sesion.puedeVender) {
      return const _SinPermiso(mensaje: 'No tiene el permiso "Crear Ventas".');
    }

    return Column(
      children: [
        _Buscador(
          controlador: _busqueda,
          escaneando: vm.escaneando,
          alBuscar: vm.buscar,
          alLimpiar: () {
            _busqueda.clear();
            vm.limpiarBusqueda();
          },
          alEscanear: vm.alternarEscaner,
        ),
        if (vm.escaneando)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
            child: SizedBox(
              // Alto fijo y contenido: deja espacio para varias líneas del carrito.
              height: (MediaQuery.of(context).size.height * 0.24).clamp(150.0, 200.0),
              child: EscanerCamara(alLeer: vm.agregarPorCodigo, alCerrar: vm.cerrarEscaner),
            ),
          ),
        Expanded(
          child: vm.buscando
              ? _ResultadosBusqueda(resultados: vm.resultados, alAgregar: vm.agregar)
              : _Carrito(vm: vm, alLimpiar: _confirmarLimpiar, alEditarCantidad: _editarCantidad),
        ),
        _BarraCobro(
          total: vm.subtotal,
          items: vm.cantidadItems,
          habilitado: !vm.vacio,
          alRevisar: _revisarOrden,
        ),
      ],
    );
  }
}

/// Diálogo para escribir la cantidad de una línea del carrito.
///
/// Tiene estado propio para que el `TextEditingController` se libere recién
/// cuando el diálogo termina de cerrarse (si se libera antes, Flutter lanza
/// «Tried to build dirty widget in the wrong build scope»).
class _DialogoCantidad extends StatefulWidget {
  const _DialogoCantidad({required this.item, required this.disponible});

  final ItemCarrito item;
  final int disponible;

  @override
  State<_DialogoCantidad> createState() => _DialogoCantidadState();
}

class _DialogoCantidadState extends State<_DialogoCantidad> {
  late final TextEditingController _control = TextEditingController(
    text: '${widget.item.cantidad}',
  )..selection = TextSelection(
    baseOffset: 0,
    extentOffset: '${widget.item.cantidad}'.length,
  );

  @override
  void dispose() {
    _control.dispose();
    super.dispose();
  }

  void _aceptar() {
    final texto = _control.text.trim();
    // Vacío = cancelar, así un borrón no deja la línea en cero por accidente.
    Navigator.pop(context, texto.isEmpty ? null : int.tryParse(texto));
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;

    return AlertDialog(
      title: Text(item.nombre, style: const TextStyle(fontSize: 16)),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _control,
            autofocus: true,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 26, fontWeight: FontWeight.w800),
            onSubmitted: (_) => _aceptar(),
            decoration: InputDecoration(
              labelText: 'Cantidad',
              suffixText: item.unidad,
              helperText: 'Disponible: ${widget.disponible} ${item.unidad}',
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'Precio unitario ${dinero(item.precioVenta)}',
            style: const TextStyle(fontSize: 12, color: AppColores.textoSuave),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context, 0),
          style: TextButton.styleFrom(foregroundColor: AppColores.rojo),
          child: const Text('Quitar'),
        ),
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancelar')),
        TextButton(onPressed: _aceptar, child: const Text('Aceptar')),
      ],
    );
  }
}

class _Buscador extends StatelessWidget {
  const _Buscador({
    required this.controlador,
    required this.escaneando,
    required this.alBuscar,
    required this.alLimpiar,
    required this.alEscanear,
  });

  final TextEditingController controlador;
  final bool escaneando;
  final ValueChanged<String> alBuscar;
  final VoidCallback alLimpiar;
  final VoidCallback alEscanear;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: controlador,
              onChanged: alBuscar,
              textInputAction: TextInputAction.search,
              style: const TextStyle(fontSize: 14),
              decoration: InputDecoration(
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                hintText: 'Buscar producto o código',
                prefixIcon: const Icon(Icons.search, size: 19),
                prefixIconConstraints: const BoxConstraints(minWidth: 38, minHeight: 38),
                suffixIcon: controlador.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close, size: 17),
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(minWidth: 38, minHeight: 38),
                        onPressed: alLimpiar,
                      ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Material(
            color: escaneando ? AppColores.primario : Colors.white,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              onTap: alEscanear,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                height: 42,
                width: 42,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: escaneando ? AppColores.primario : AppColores.borde),
                ),
                child: Icon(
                  escaneando ? Icons.close : Icons.qr_code_scanner,
                  color: escaneando ? Colors.white : AppColores.primario,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Carrito extends StatelessWidget {
  const _Carrito({required this.vm, required this.alLimpiar, required this.alEditarCantidad});

  final VentaVistaModelo vm;
  final VoidCallback alLimpiar;
  final Future<void> Function(ItemCarrito item) alEditarCantidad;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 9, 16, 7),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Flexible(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Text(
                            'Artículos',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: AppColores.texto,
                            ),
                          ),
                          const SizedBox(width: 8),
                          if (!vm.vacio)
                            TextButton(
                              onPressed: alLimpiar,
                              style: TextButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size(0, 0),
                                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                foregroundColor: AppColores.rojo,
                              ),
                              child: const Text(
                                'LIMPIAR',
                                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 1),
                      Text(
                        '${vm.cantidadItems} ${vm.cantidadItems == 1 ? 'item' : 'items'}'
                        '${vm.lineas > 0 ? ' · ${vm.lineas} productos' : ''}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 11, color: AppColores.textoSuave),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    const Text(
                      'TOTAL',
                      style: TextStyle(
                        fontSize: 10,
                        letterSpacing: 0.6,
                        fontWeight: FontWeight.w700,
                        color: AppColores.textoSuave,
                      ),
                    ),
                    Text(
                      dinero(vm.subtotal),
                      style: const TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w800,
                        color: AppColores.primario,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: vm.vacio
                ? const EstadoVacio(
                    icono: Icons.shopping_basket_outlined,
                    titulo: 'Carrito vacío',
                    detalle:
                        'Busque un producto o escanee su código de barras para empezar la venta.',
                  )
                : ListView.separated(
                    padding: const EdgeInsets.only(bottom: 4),
                    itemCount: vm.items.length,
                    separatorBuilder: (_, _) => const Divider(height: 1, indent: 58),
                    itemBuilder: (context, indice) {
                      final item = vm.items[indice];
                      return _FilaCarrito(
                        item: item,
                        alSumar: () => vm.aumentar(item),
                        alRestar: () => vm.disminuir(item),
                        alQuitar: () => vm.quitar(item),
                        alEditarCantidad: () => alEditarCantidad(item),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}

class _FilaCarrito extends StatelessWidget {
  const _FilaCarrito({
    required this.item,
    required this.alSumar,
    required this.alRestar,
    required this.alQuitar,
    required this.alEditarCantidad,
  });

  final ItemCarrito item;
  final VoidCallback alSumar;
  final VoidCallback alRestar;
  final VoidCallback alQuitar;
  final VoidCallback alEditarCantidad;

  @override
  Widget build(BuildContext context) {
    return Dismissible(
      key: ValueKey('carrito-${item.id}'),
      direction: DismissDirection.endToStart,
      background: Container(
        color: AppColores.rojo.withValues(alpha: 0.1),
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Icon(Icons.delete_outline, color: AppColores.rojo),
      ),
      onDismissed: (_) => alQuitar(),
      // Fila compacta (~46 px) para que entren muchos productos en pantalla.
      // Al tocarla se puede escribir la cantidad directamente.
      child: InkWell(
        onTap: alEditarCantidad,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 5, 10, 5),
          child: Row(
            children: [
              FotoProducto(foto: item.foto, tamano: 36, radio: 9),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      item.nombre,
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
                      '${dinero(item.precioVenta)} · ${item.unidad}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColores.textoSuave,
                        height: 1.2,
                      ),
                    ),
                  ],
                ),
              ),
              _Contador(
                cantidad: item.cantidad,
                alSumar: alSumar,
                alRestar: alRestar,
                alEscribir: alEditarCantidad,
              ),
              SizedBox(
                width: 66,
                child: Text(
                  dinero(item.total),
                  textAlign: TextAlign.right,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColores.primario,
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

class _Contador extends StatelessWidget {
  const _Contador({
    required this.cantidad,
    required this.alSumar,
    required this.alRestar,
    required this.alEscribir,
  });

  final int cantidad;
  final VoidCallback alSumar;
  final VoidCallback alRestar;
  final VoidCallback alEscribir;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _BotonContador(icono: Icons.remove, alTocar: alRestar),
        // El número es tocable: abre el teclado para escribir la cantidad.
        InkWell(
          onTap: alEscribir,
          borderRadius: BorderRadius.circular(8),
          child: Container(
            width: 34,
            height: 28,
            alignment: Alignment.center,
            child: Text(
              '$cantidad',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                decoration: TextDecoration.underline,
                decorationColor: AppColores.borde,
                decorationThickness: 2,
              ),
            ),
          ),
        ),
        _BotonContador(icono: Icons.add, alTocar: alSumar),
      ],
    );
  }
}

class _BotonContador extends StatelessWidget {
  const _BotonContador({required this.icono, required this.alTocar});

  final IconData icono;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColores.fondo,
      borderRadius: BorderRadius.circular(9),
      child: InkWell(
        onTap: alTocar,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          height: 28,
          width: 28,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: AppColores.borde),
          ),
          child: Icon(icono, size: 16, color: AppColores.texto),
        ),
      ),
    );
  }
}

class _ResultadosBusqueda extends StatelessWidget {
  const _ResultadosBusqueda({required this.resultados, required this.alAgregar});

  final List<Producto> resultados;
  final Future<void> Function(Producto producto, {int cantidad}) alAgregar;

  @override
  Widget build(BuildContext context) {
    if (resultados.isEmpty) {
      return const EstadoVacio(
        icono: Icons.search_off,
        titulo: 'Sin resultados',
        detalle: 'Verifique el nombre o sincronice el catálogo desde la pestaña Productos.',
      );
    }

    // Resultados compactos: se ven muchos más productos por pantalla.
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 10),
      itemCount: resultados.length,
      separatorBuilder: (_, _) => const SizedBox(height: 6),
      itemBuilder: (context, indice) {
        final producto = resultados[indice];
        final sinStock = producto.stock <= 0;

        return Tarjeta(
          padding: const EdgeInsets.fromLTRB(10, 7, 8, 7),
          alTocar: sinStock ? null : () => alAgregar(producto),
          hijo: Row(
            children: [
              FotoProducto(foto: producto.foto, tamano: 38, radio: 10),
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
                    Row(
                      children: [
                        Text(
                          dinero(producto.precioVenta),
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: AppColores.primario,
                            fontSize: 12,
                            height: 1.3,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            sinStock ? 'Sin stock' : '${producto.stock} ${producto.unidad}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              height: 1.3,
                              fontWeight: FontWeight.w600,
                              color: sinStock ? AppColores.rojo : AppColores.textoSuave,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(
                sinStock ? Icons.block : Icons.add_circle,
                color: sinStock ? AppColores.textoSuave : AppColores.primario,
                size: 24,
              ),
            ],
          ),
        );
      },
    );
  }
}

class _BarraCobro extends StatelessWidget {
  const _BarraCobro({
    required this.total,
    required this.items,
    required this.habilitado,
    required this.alRevisar,
  });

  final double total;
  final int items;
  final bool habilitado;
  final VoidCallback alRevisar;

  @override
  Widget build(BuildContext context) {
    // El margen inferior deja libre la parte del botón circular del menú que
    // sobresale por encima de la barra: si no, tapa "Revisar Orden".
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 6, 12, 26),
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: AppColores.borde)),
      ),
      child: SizedBox(
        height: 46,
        child: ElevatedButton.icon(
          onPressed: habilitado ? alRevisar : null,
          icon: const Icon(Icons.receipt_long, size: 20),
          label: Text(habilitado ? 'Revisar Orden · ${dinero(total)}' : 'Revisar Orden'),
          style: ElevatedButton.styleFrom(
            padding: const EdgeInsets.symmetric(vertical: 8),
            textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            disabledBackgroundColor: AppColores.borde,
            disabledForegroundColor: AppColores.textoSuave,
          ),
        ),
      ),
    );
  }
}

class _SinPermiso extends StatelessWidget {
  const _SinPermiso({required this.mensaje});

  final String mensaje;

  @override
  Widget build(BuildContext context) {
    return EstadoVacio(icono: Icons.lock_outline, titulo: 'Acceso restringido', detalle: mensaje);
  }
}
