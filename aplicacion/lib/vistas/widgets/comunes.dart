import 'package:flutter/material.dart';

import '../../core/tema.dart';

/// Estado vacío reutilizable (sin resultados, sin ventas, etc.).
class EstadoVacio extends StatelessWidget {
  const EstadoVacio({
    super.key,
    required this.icono,
    required this.titulo,
    this.detalle,
    this.accion,
  });

  final IconData icono;
  final String titulo;
  final String? detalle;
  final Widget? accion;

  @override
  Widget build(BuildContext context) {
    // Se adapta al alto disponible: con el escáner abierto queda poco espacio,
    // así que el contenido se encoge y, si aún no entra, se puede desplazar.
    return LayoutBuilder(
      builder: (context, restricciones) {
        final apretado = restricciones.maxHeight < 230;
        final tamanoIcono = apretado ? 24.0 : 34.0;

        return SingleChildScrollView(
          padding: EdgeInsets.symmetric(horizontal: 26, vertical: apretado ? 12 : 28),
          child: ConstrainedBox(
            constraints: BoxConstraints(
              minHeight: restricciones.maxHeight.isFinite
                  ? restricciones.maxHeight - (apretado ? 24 : 56)
                  : 0,
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: EdgeInsets.all(apretado ? 12 : 18),
                  decoration: const BoxDecoration(
                    color: AppColores.primarioSuave,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icono, size: tamanoIcono, color: AppColores.primario),
                ),
                SizedBox(height: apretado ? 10 : 16),
                Text(
                  titulo,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: apretado ? 14 : 16,
                    fontWeight: FontWeight.w700,
                    color: AppColores.texto,
                  ),
                ),
                if (detalle != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    detalle!,
                    textAlign: TextAlign.center,
                    maxLines: apretado ? 2 : 4,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: AppColores.textoSuave,
                      height: 1.35,
                      fontSize: apretado ? 12 : 14,
                    ),
                  ),
                ],
                if (accion != null) ...[SizedBox(height: apretado ? 12 : 18), accion!],
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Etiqueta de color para estados y formas de pago.
class Etiqueta extends StatelessWidget {
  const Etiqueta({super.key, required this.texto, required this.color, this.icono});

  final String texto;
  final Color color;
  final IconData? icono;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icono != null) ...[Icon(icono, size: 13, color: color), const SizedBox(width: 4)],
          Text(
            texto,
            style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color),
          ),
        ],
      ),
    );
  }
}

/// Tarjeta blanca con borde suave, base visual de casi todas las listas.
class Tarjeta extends StatelessWidget {
  const Tarjeta({super.key, required this.hijo, this.padding, this.alTocar});

  final Widget hijo;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: alTocar,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: padding ?? const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColores.borde),
          ),
          child: hijo,
        ),
      ),
    );
  }
}

/// Aviso flotante. Aparece **arriba** para no tapar el carrito ni el botón de
/// cobro, que es donde está la vista del vendedor.
void mostrarMensaje(BuildContext context, String mensaje, {bool esError = false}) {
  final medidas = MediaQuery.of(context);
  final alto = medidas.size.height;
  final margenSuperior = medidas.padding.top + kToolbarHeight + 8;
  // El SnackBar flotante se sube dejando casi todo el alto como margen inferior.
  final margenInferior = (alto - margenSuperior - 64).clamp(0.0, alto);

  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              esError ? Icons.error_outline : Icons.check_circle_outline,
              color: Colors.white,
              size: 18,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                mensaje,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13),
              ),
            ),
          ],
        ),
        backgroundColor: esError ? AppColores.rojo : AppColores.primarioOscuro,
        behavior: SnackBarBehavior.floating,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        margin: EdgeInsets.fromLTRB(12, 0, 12, margenInferior),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        duration: Duration(milliseconds: esError ? 2600 : 1400),
      ),
    );
}
