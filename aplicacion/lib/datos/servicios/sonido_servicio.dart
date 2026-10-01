import 'package:audioplayers/audioplayers.dart';

/// Pitidos de confirmación del punto de venta.
///
/// Cada tono se carga una sola vez, la primera que suena, y el reproductor
/// queda listo en `PlayerMode.lowLatency` (SoundPool en Android), así el
/// segundo escaneo no espera a que se abra el archivo otra vez.
///
/// Todo va envuelto en `try/catch`: si el dispositivo no puede reproducir —o
/// se corre en un test, sin plugin— la venta sigue igual y sólo se pierde el
/// sonido. Tras el primer fallo se deja de intentar, para no pagar el costo
/// en cada lectura.
class SonidoServicio {
  static const _pitido = 'sonidos/pitido.wav';
  static const _error = 'sonidos/error.wav';

  /// Se guarda el `Future` y no el reproductor para que dos pitidos seguidos
  /// no creen dos reproductores del mismo tono.
  final Map<String, Future<AudioPlayer>> _reproductores = {};
  bool _disponible = true;

  /// Producto agregado: un pitido corto y agudo.
  Future<void> exito() => _reproducir(_pitido);

  /// Código desconocido o sin stock: dos tonos graves.
  Future<void> error() => _reproducir(_error);

  Future<void> _reproducir(String recurso) async {
    if (!_disponible) return;
    try {
      final reproductor = await (_reproductores[recurso] ??= _preparar(recurso));
      // `stop` antes de sonar permite encadenar escaneos rápidos: el pitido
      // anterior se corta en vez de ignorarse el nuevo.
      await reproductor.stop();
      await reproductor.resume();
    } catch (_) {
      _reproductores.remove(recurso);
      _disponible = false;
    }
  }

  Future<AudioPlayer> _preparar(String recurso) async {
    final reproductor = AudioPlayer();
    await reproductor.setPlayerMode(PlayerMode.lowLatency);
    await reproductor.setReleaseMode(ReleaseMode.stop);
    await reproductor.setSource(AssetSource(recurso));
    return reproductor;
  }

  Future<void> dispose() async {
    for (final pendiente in _reproductores.values) {
      try {
        await (await pendiente).dispose();
      } catch (_) {
        // Nunca llegó a cargarse o ya estaba liberado.
      }
    }
    _reproductores.clear();
  }
}
