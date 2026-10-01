import '../datos/repositorios/sesion_repositorio.dart';
import '../datos/servicios/api_servicio.dart';
import '../modelos/usuario.dart';
import 'vista_modelo_base.dart';

enum EstadoSesion { iniciando, invitado, autenticado }

/// ViewModel global de la sesión: decide si se muestra el login o la app.
class SesionVistaModelo extends VistaModeloBase {
  SesionVistaModelo(this._repositorio, this._api);

  final SesionRepositorio _repositorio;
  final ApiServicio _api;

  EstadoSesion _estado = EstadoSesion.iniciando;
  Usuario? _usuario;
  String _urlApi = ApiServicio.urlPorDefecto;

  EstadoSesion get estado => _estado;
  Usuario? get usuario => _usuario;
  String get urlApi => _urlApi;
  bool get autenticado => _estado == EstadoSesion.autenticado;

  bool puede(String permiso) => _usuario?.tienePermiso(permiso) ?? false;
  bool get puedeVender => puede('Crear Ventas');
  bool get puedeVerVentas => puede('Ver Ventas');
  bool get puedeVerProductos => puede('Ver Productos');

  /// Se llama al abrir la app: restaura la sesión guardada en SQLite.
  Future<void> iniciar() async {
    _urlApi = await _repositorio.leerUrlApi();
    _api.urlBase = _urlApi;

    final sesion = await _repositorio.sesionGuardada();
    if (sesion == null || sesion.token.isEmpty) {
      _estado = EstadoSesion.invitado;
      notificar();
      return;
    }

    _api.token = sesion.token;
    _usuario = sesion.usuario;
    _estado = EstadoSesion.autenticado;
    notificar();

    // Revalidación en segundo plano: si el token murió, se vuelve al login.
    try {
      _usuario = await _repositorio.refrescarUsuario();
      notificar();
    } on ApiExcepcion catch (e) {
      if (e.noAutorizado) await cerrarSesion(avisarApi: false);
    } catch (_) {
      // Sin conexión: se sigue trabajando con los permisos cacheados.
    }
  }

  Future<bool> iniciarSesion(String usuario, String contrasena) async {
    final sesion = await ejecutar(() => _repositorio.iniciarSesion(usuario, contrasena));
    if (sesion == null) return false;

    _usuario = sesion.usuario;
    _estado = EstadoSesion.autenticado;
    notificar();
    return true;
  }

  Future<void> guardarUrlApi(String url) async {
    await _repositorio.guardarUrlApi(url);
    _urlApi = await _repositorio.leerUrlApi();
    notificar();
  }

  Future<void> cerrarSesion({bool avisarApi = true}) async {
    await _repositorio.cerrarSesion(avisarApi: avisarApi);
    _usuario = null;
    _estado = EstadoSesion.invitado;
    notificar();
  }
}
