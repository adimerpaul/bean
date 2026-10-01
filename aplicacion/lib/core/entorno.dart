import 'package:flutter/foundation.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';

/// Configuración por entorno, igual que en `front/`:
///
/// - `.env.development` → se usa en debug/profile (servidor local).
/// - `.env.production`  → se usa en release (`flutter build apk --release`).
class Entorno {
  static const String _urlRespaldo = 'https://bbean.tuprogam.com/api';

  static String get archivo => kReleaseMode ? '.env.production' : '.env.development';

  /// Carga el archivo del entorno. Si falta, la app sigue con los valores
  /// de respaldo en lugar de no arrancar.
  static Future<void> cargar() async {
    try {
      await dotenv.load(fileName: archivo);
    } catch (_) {
      dotenv.loadFromString(envString: 'API_URL=$_urlRespaldo');
    }
  }

  /// URL base de la API, con el sufijo `/api` y sin barra final.
  static String get apiUrl {
    final valor = (_leer('API_URL') ?? _urlRespaldo).trim();
    final limpia = valor.replaceAll(RegExp(r'/+$'), '');
    return limpia.endsWith('/api') ? limpia : '$limpia/api';
  }

  static String get version => _leer('APP_VERSION') ?? '1.0.0';

  /// Nunca lanza: si `cargar()` todavía no corrió (tests, arranque temprano)
  /// se usan los valores de respaldo.
  static String? _leer(String clave) =>
      dotenv.isInitialized ? dotenv.maybeGet(clave) : null;
}
