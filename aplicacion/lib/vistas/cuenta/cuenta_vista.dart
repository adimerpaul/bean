import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/entorno.dart';
import '../../core/formato.dart';
import '../../core/tema.dart';
import '../../vistamodelos/productos_vistamodelo.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../widgets/comunes.dart';

class CuentaVista extends StatelessWidget {
  const CuentaVista({super.key});

  Future<void> _cerrarSesion(BuildContext context) async {
    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('Se borrará la sesión y el carrito guardados en este teléfono.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto, false), child: const Text('Cancelar')),
          TextButton(
            onPressed: () => Navigator.pop(contexto, true),
            style: TextButton.styleFrom(foregroundColor: AppColores.rojo),
            child: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );

    if (confirmado != true || !context.mounted) return;
    await context.read<SesionVistaModelo>().cerrarSesion();
  }

  Future<void> _sincronizar(BuildContext context) async {
    final vm = context.read<ProductosVistaModelo>();
    final cantidad = await vm.sincronizar();
    if (!context.mounted) return;
    mostrarMensaje(
      context,
      cantidad == null
          ? (vm.error ?? 'No se pudo sincronizar')
          : '$cantidad productos actualizados',
      esError: cantidad == null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionVistaModelo>();
    final productos = context.watch<ProductosVistaModelo>();
    final usuario = sesion.usuario;

    return ListView(
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 24),
      children: [
        Tarjeta(
          padding: const EdgeInsets.all(18),
          hijo: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: AppColores.primario,
                child: Text(
                  usuario?.iniciales ?? '?',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      usuario?.nombre ?? 'Sin sesión',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: AppColores.texto,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '@${usuario?.username ?? ''}',
                      style: const TextStyle(color: AppColores.textoSuave),
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
              const Text('Permisos', style: TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 10),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  for (final permiso in (usuario?.permisos ?? const <String>[]))
                    Etiqueta(texto: permiso, color: AppColores.primario),
                  if ((usuario?.permisos ?? const []).isEmpty)
                    const Text(
                      'Sin permisos asignados',
                      style: TextStyle(color: AppColores.textoSuave, fontSize: 13),
                    ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Tarjeta(
          padding: EdgeInsets.zero,
          hijo: Column(
            children: [
              ListTile(
                leading: const Icon(Icons.dns_outlined, color: AppColores.primario),
                title: const Text('Servidor'),
                subtitle: Text(sesion.urlApi, style: const TextStyle(fontSize: 12)),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.sync, color: AppColores.primario),
                title: const Text('Sincronizar catálogo'),
                subtitle: Text(
                  productos.ultimaSincronizacion == null
                      ? 'Nunca sincronizado'
                      : 'Última vez: ${fechaHora(productos.ultimaSincronizacion)}',
                  style: const TextStyle(fontSize: 12),
                ),
                trailing: productos.sincronizando
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2.2, color: AppColores.primario),
                      )
                    : const Icon(Icons.chevron_right),
                onTap: productos.sincronizando ? null : () => _sincronizar(context),
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.logout, color: AppColores.rojo),
                title: const Text('Cerrar sesión', style: TextStyle(color: AppColores.rojo)),
                onTap: () => _cerrarSesion(context),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        Center(
          child: Text(
            'Bean Ventas · v${Entorno.version}',
            style: const TextStyle(color: AppColores.textoSuave, fontSize: 12),
          ),
        ),
      ],
    );
  }
}
