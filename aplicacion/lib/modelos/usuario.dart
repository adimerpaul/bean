import '../core/formato.dart';

class Usuario {
  Usuario({
    required this.id,
    required this.nombre,
    required this.username,
    required this.permisos,
  });

  final int id;
  final String nombre;
  final String username;
  final List<String> permisos;

  bool tienePermiso(String permiso) => permisos.contains(permiso);

  /// Iniciales para el avatar (máximo dos letras).
  String get iniciales {
    final partes = nombre.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (partes.isEmpty) return '?';
    final primera = partes.first.substring(0, 1);
    if (partes.length == 1) return primera.toUpperCase();
    return (primera + partes[1].substring(0, 1)).toUpperCase();
  }

  factory Usuario.desdeApi(Map<String, dynamic> json) => Usuario(
        id: aEntero(json['id']),
        nombre: (json['name'] ?? '').toString(),
        username: (json['username'] ?? '').toString(),
        permisos: ((json['permissions'] ?? const []) as List)
            .map((p) => (p is Map ? p['name'] : p).toString())
            .toList(),
      );
}
