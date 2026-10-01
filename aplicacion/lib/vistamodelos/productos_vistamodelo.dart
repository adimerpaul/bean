import 'dart:async';

import '../datos/repositorios/producto_repositorio.dart';
import '../modelos/producto.dart';
import 'vista_modelo_base.dart';

/// ViewModel del catálogo: lee de SQLite y sincroniza contra la API.
class ProductosVistaModelo extends VistaModeloBase {
  ProductosVistaModelo(this._repositorio);

  final ProductoRepositorio _repositorio;

  List<Producto> _productos = [];
  String _busqueda = '';
  bool _sincronizando = false;
  DateTime? _ultimaSincronizacion;
  Timer? _rebote;

  List<Producto> get productos => List.unmodifiable(_productos);
  String get busqueda => _busqueda;
  bool get sincronizando => _sincronizando;
  DateTime? get ultimaSincronizacion => _ultimaSincronizacion;
  bool get catalogoVacio => _productos.isEmpty && _busqueda.isEmpty;

  Future<void> cargar() async {
    await ejecutar(() async {
      _productos = await _repositorio.buscarLocal(_busqueda, limite: 300);
      _ultimaSincronizacion = await _repositorio.ultimaSincronizacion();
    });

    // La primera vez el catálogo local está vacío: se descarga solo.
    if (_productos.isEmpty && _busqueda.isEmpty && _ultimaSincronizacion == null) {
      await sincronizar();
    }
  }

  void buscar(String texto) {
    _busqueda = texto;
    notificar();
    _rebote?.cancel();
    _rebote = Timer(const Duration(milliseconds: 220), () async {
      _productos = await _repositorio.buscarLocal(texto, limite: 300);
      notificar();
    });
  }

  Future<int?> sincronizar() async {
    _sincronizando = true;
    notificar();

    final cantidad = await ejecutar(() => _repositorio.sincronizar(), mostrarCarga: false);
    _productos = await _repositorio.buscarLocal(_busqueda, limite: 300);
    _ultimaSincronizacion = await _repositorio.ultimaSincronizacion();
    _sincronizando = false;
    notificar();
    return cantidad;
  }

  @override
  void dispose() {
    _rebote?.cancel();
    super.dispose();
  }
}
