import 'package:flutter/foundation.dart';

import '../datos/servicios/api_servicio.dart';

/// Base de todos los ViewModel: maneja el estado de carga y el mensaje de error
/// para que las vistas sólo se preocupen de dibujar.
abstract class VistaModeloBase extends ChangeNotifier {
  bool _cargando = false;
  String? _error;
  bool _desechado = false;

  bool get cargando => _cargando;
  String? get error => _error;
  bool get hayError => _error != null;

  @protected
  void establecerCargando(bool valor) {
    if (_cargando == valor) return;
    _cargando = valor;
    notificar();
  }

  @protected
  void establecerError(String? mensaje) {
    _error = mensaje;
    notificar();
  }

  void limpiarError() => establecerError(null);

  /// Ejecuta una operación asíncrona controlando carga y errores.
  /// Devuelve `null` si falló.
  @protected
  Future<T?> ejecutar<T>(Future<T> Function() operacion, {bool mostrarCarga = true}) async {
    if (mostrarCarga) establecerCargando(true);
    _error = null;
    try {
      return await operacion();
    } on ApiExcepcion catch (e) {
      establecerError(e.mensaje);
      return null;
    } catch (e) {
      establecerError('Ocurrió un error inesperado: $e');
      return null;
    } finally {
      if (mostrarCarga) establecerCargando(false);
    }
  }

  @protected
  void notificar() {
    if (_desechado) return;
    notifyListeners();
  }

  @override
  void dispose() {
    _desechado = true;
    super.dispose();
  }
}
