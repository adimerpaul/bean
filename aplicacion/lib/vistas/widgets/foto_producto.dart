import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/tema.dart';
import '../../datos/servicios/api_servicio.dart';

/// Miniatura de producto con respaldo cuando no hay foto o falla la descarga.
class FotoProducto extends StatelessWidget {
  const FotoProducto({super.key, required this.foto, this.tamano = 46, this.radio = 12});

  final String? foto;
  final double tamano;
  final double radio;

  @override
  Widget build(BuildContext context) {
    final url = context.read<ApiServicio>().urlFoto(foto);

    return ClipRRect(
      borderRadius: BorderRadius.circular(radio),
      child: Container(
        width: tamano,
        height: tamano,
        color: AppColores.primarioSuave,
        child: url == null
            ? _respaldo()
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _respaldo(),
                loadingBuilder: (context, hijo, progreso) =>
                    progreso == null ? hijo : _respaldo(),
              ),
      ),
    );
  }

  Widget _respaldo() => Center(
        child: Icon(Icons.local_drink_outlined, size: tamano * 0.5, color: AppColores.primario),
      );
}
