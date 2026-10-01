import 'dart:convert';

import 'package:sqflite/sqflite.dart';

import '../../modelos/usuario.dart';
import '../servicios/api_servicio.dart';
import '../servicios/base_datos.dart';

/// Datos de la sesión guardados en SQLite.
class SesionLocal {
  SesionLocal({required this.token, required this.usuario});

  final String token;
  final Usuario usuario;
}

/// Acceso a login/logout y persistencia local de la sesión.
class SesionRepositorio {
  SesionRepositorio(this._api);

  final ApiServicio _api;

  static const String _claveUrl = 'url_api';

  Future<String> leerUrlApi() async {
    final guardada = await BaseDatos.ajuste(_claveUrl);
    return (guardada == null || guardada.isEmpty) ? ApiServicio.urlPorDefecto : guardada;
  }

  Future<void> guardarUrlApi(String url) async {
    final limpia = url.trim().replaceAll(RegExp(r'/+$'), '');
    await BaseDatos.guardarAjuste(_claveUrl, limpia);
    _api.urlBase = limpia;
  }

  /// Autentica contra la API y guarda token + usuario + permisos en SQLite.
  Future<SesionLocal> iniciarSesion(String usuario, String contrasena) async {
    final respuesta = await _api.publicar('/login', cuerpo: {
      'username': usuario.trim(),
      'password': contrasena,
    }) as Map<String, dynamic>;

    final token = (respuesta['token'] ?? '').toString();
    final datosUsuario = Usuario.desdeApi(Map<String, dynamic>.from(respuesta['user'] as Map));
    final sesion = SesionLocal(token: token, usuario: datosUsuario);
    await guardarSesion(sesion);
    _api.token = token;
    return sesion;
  }

  Future<void> guardarSesion(SesionLocal sesion) async {
    final db = await BaseDatos.instancia;
    await db.insert(
      'sesion',
      {
        'id': 1,
        'token': sesion.token,
        'user_id': sesion.usuario.id,
        'nombre': sesion.usuario.nombre,
        'username': sesion.usuario.username,
        'permisos': jsonEncode(sesion.usuario.permisos),
        'actualizado': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  /// Sesión guardada en el dispositivo (permite abrir la app sin volver a loguear).
  Future<SesionLocal?> sesionGuardada() async {
    final db = await BaseDatos.instancia;
    final filas = await db.query('sesion', where: 'id = 1', limit: 1);
    if (filas.isEmpty) return null;
    final fila = filas.first;
    final permisos = (jsonDecode((fila['permisos'] ?? '[]').toString()) as List)
        .map((p) => p.toString())
        .toList();
    return SesionLocal(
      token: (fila['token'] ?? '').toString(),
      usuario: Usuario(
        id: (fila['user_id'] as int?) ?? 0,
        nombre: (fila['nombre'] ?? '').toString(),
        username: (fila['username'] ?? '').toString(),
        permisos: permisos,
      ),
    );
  }

  /// Revalida el token contra la API y actualiza los permisos locales.
  Future<Usuario> refrescarUsuario() async {
    final respuesta = await _api.obtener('/me') as Map<String, dynamic>;
    final usuario = Usuario.desdeApi(respuesta);
    final actual = await sesionGuardada();
    if (actual != null) {
      await guardarSesion(SesionLocal(token: actual.token, usuario: usuario));
    }
    return usuario;
  }

  Future<void> cerrarSesion({bool avisarApi = true}) async {
    if (avisarApi) {
      try {
        await _api.publicar('/logout');
      } catch (_) {
        // Si el servidor no responde igual se limpia la sesión local.
      }
    }
    final db = await BaseDatos.instancia;
    await db.delete('sesion');
    await db.delete('carrito');
    _api.token = null;
  }
}
