import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../../core/entorno.dart';

/// Error de negocio devuelto por la API (mensaje ya listo para mostrar).
class ApiExcepcion implements Exception {
  ApiExcepcion(this.mensaje, {this.codigo = 0});

  final String mensaje;
  final int codigo;

  bool get noAutorizado => codigo == 401;
  bool get sinPermiso => codigo == 403;

  @override
  String toString() => mensaje;
}

/// Cliente HTTP de la API de Bean.
///
/// Es un servicio sin estado de UI: sólo conoce la URL base y el token.
class ApiServicio {
  ApiServicio({String? urlBase, this.token}) : urlBase = urlBase ?? urlPorDefecto;

  /// URL del archivo .env del entorno activo.
  static String get urlPorDefecto => Entorno.apiUrl;

  /// URL base de la API. Se puede cambiar desde la pantalla de login.
  String urlBase;

  /// Token Sanctum de la sesión activa.
  String? token;

  /// Base para las imágenes: la URL de la API sin el sufijo `/api`.
  String get urlImagenes {
    final limpia = urlBase.replaceAll(RegExp(r'/+$'), '');
    return limpia.endsWith('/api') ? limpia.substring(0, limpia.length - 4) : limpia;
  }

  String? urlFoto(String? foto) {
    if (foto == null || foto.isEmpty) return null;
    if (foto.startsWith('http')) return foto;
    return '$urlImagenes/images/$foto';
  }

  Map<String, String> get _cabeceras => {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (token != null && token!.isNotEmpty) 'Authorization': 'Bearer $token',
      };

  Uri _uri(String ruta, [Map<String, dynamic>? parametros]) {
    final limpia = urlBase.replaceAll(RegExp(r'/+$'), '');
    final consulta = <String, String>{};
    parametros?.forEach((clave, valor) {
      if (valor == null) return;
      final texto = valor.toString();
      if (texto.isEmpty) return;
      consulta[clave] = texto;
    });
    return Uri.parse('$limpia$ruta').replace(queryParameters: consulta.isEmpty ? null : consulta);
  }

  Future<dynamic> _enviar(Future<http.Response> Function() peticion) async {
    http.Response respuesta;
    try {
      respuesta = await peticion().timeout(const Duration(seconds: 25));
    } on SocketException {
      throw ApiExcepcion('Sin conexión con el servidor. Verifique su internet.');
    } on HttpException {
      throw ApiExcepcion('No se pudo comunicar con el servidor.');
    } catch (error) {
      throw ApiExcepcion('No se pudo comunicar con el servidor ($error).');
    }

    dynamic cuerpo;
    if (respuesta.body.isNotEmpty) {
      try {
        cuerpo = jsonDecode(respuesta.body);
      } catch (_) {
        cuerpo = null;
      }
    }

    if (respuesta.statusCode >= 200 && respuesta.statusCode < 300) return cuerpo;

    throw ApiExcepcion(_mensajeDeError(cuerpo, respuesta.statusCode), codigo: respuesta.statusCode);
  }

  String _mensajeDeError(dynamic cuerpo, int estado) {
    if (cuerpo is Map) {
      final errores = cuerpo['errors'];
      if (errores is Map && errores.isNotEmpty) {
        final primero = errores.values.first;
        if (primero is List && primero.isNotEmpty) return primero.first.toString();
      }
      final mensaje = cuerpo['message'];
      if (mensaje is String && mensaje.isNotEmpty) return mensaje;
    }
    return switch (estado) {
      401 => 'Su sesión expiró, vuelva a iniciar sesión.',
      403 => 'No tiene permiso para realizar esta acción.',
      404 => 'No se encontró el recurso solicitado.',
      _ => 'Ocurrió un error en el servidor ($estado).',
    };
  }

  Future<dynamic> obtener(String ruta, {Map<String, dynamic>? parametros}) =>
      _enviar(() => http.get(_uri(ruta, parametros), headers: _cabeceras));

  Future<dynamic> publicar(String ruta, {Map<String, dynamic>? cuerpo}) => _enviar(
        () => http.post(_uri(ruta), headers: _cabeceras, body: jsonEncode(cuerpo ?? const {})),
      );

  Future<dynamic> actualizar(String ruta, {Map<String, dynamic>? cuerpo}) => _enviar(
        () => http.put(_uri(ruta), headers: _cabeceras, body: jsonEncode(cuerpo ?? const {})),
      );
}
