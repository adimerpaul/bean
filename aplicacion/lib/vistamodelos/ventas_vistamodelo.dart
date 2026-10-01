import '../core/formato.dart';
import '../datos/repositorios/venta_repositorio.dart';
import '../modelos/venta.dart';
import 'vista_modelo_base.dart';

enum RangoFecha {
  hoy('Hoy'),
  semana('7 días'),
  mes('Mes'),
  todo('Todo');

  const RangoFecha(this.etiqueta);

  final String etiqueta;
}

/// ViewModel del listado de ventas, con filtro por fecha y paginación.
class VentasVistaModelo extends VistaModeloBase {
  VentasVistaModelo(this._repositorio);

  final VentaRepositorio _repositorio;

  final List<Venta> _ventas = [];
  RangoFecha _rango = RangoFecha.hoy;
  String _busqueda = '';
  int _pagina = 1;
  bool _hayMas = true;
  bool _cargandoMas = false;
  bool _desdeCache = false;

  List<Venta> get ventas => List.unmodifiable(_ventas);
  RangoFecha get rango => _rango;
  String get busqueda => _busqueda;
  bool get hayMas => _hayMas;
  bool get cargandoMas => _cargandoMas;
  bool get desdeCache => _desdeCache;

  double get totalListado =>
      _ventas.where((v) => !v.anulada).fold(0.0, (suma, v) => suma + v.total);
  int get cantidadValidas => _ventas.where((v) => !v.anulada).length;

  DateTime? get _desde {
    final hoy = DateTime.now();
    return switch (_rango) {
      RangoFecha.hoy => DateTime(hoy.year, hoy.month, hoy.day),
      RangoFecha.semana => DateTime(hoy.year, hoy.month, hoy.day).subtract(const Duration(days: 6)),
      RangoFecha.mes => DateTime(hoy.year, hoy.month, 1),
      RangoFecha.todo => null,
    };
  }

  Future<void> cargar() async {
    _pagina = 1;
    _hayMas = true;
    final resultado = await ejecutar(() => _repositorio.listar(
          busqueda: _busqueda,
          desde: _desde,
          pagina: 1,
        ));

    if (resultado == null) {
      // Sin conexión: se muestra lo último guardado en el dispositivo.
      final cache = await _repositorio.listarCache();
      _ventas
        ..clear()
        ..addAll(cache);
      _desdeCache = cache.isNotEmpty;
      _hayMas = false;
      notificar();
      return;
    }

    _desdeCache = false;
    _ventas
      ..clear()
      ..addAll(resultado);
    _hayMas = resultado.length >= 20;
    notificar();
  }

  Future<void> cargarMas() async {
    if (!_hayMas || _cargandoMas || cargando) return;
    _cargandoMas = true;
    notificar();

    final resultado = await ejecutar(
      () => _repositorio.listar(busqueda: _busqueda, desde: _desde, pagina: _pagina + 1),
      mostrarCarga: false,
    );

    if (resultado != null) {
      _pagina++;
      _ventas.addAll(resultado);
      _hayMas = resultado.length >= 20;
    } else {
      _hayMas = false;
    }
    _cargandoMas = false;
    notificar();
  }

  Future<void> cambiarRango(RangoFecha rango) async {
    if (_rango == rango) return;
    _rango = rango;
    notificar();
    await cargar();
  }

  Future<void> cambiarBusqueda(String texto) async {
    _busqueda = texto.trim();
    await cargar();
  }

  Future<Venta?> detalle(int id) => ejecutar(() => _repositorio.detalle(id), mostrarCarga: false);

  String get etiquetaRango => switch (_rango) {
        RangoFecha.hoy => 'Ventas de hoy · ${fecha(DateTime.now())}',
        RangoFecha.semana => 'Últimos 7 días',
        RangoFecha.mes => 'Este mes',
        RangoFecha.todo => 'Todas las ventas',
      };
}
