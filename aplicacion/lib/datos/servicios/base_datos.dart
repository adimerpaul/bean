import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// Base de datos local (SQLite) donde se guardan la sesión, los ajustes,
/// el catálogo de productos, el carrito en curso y las últimas ventas.
class BaseDatos {
  static Database? _db;

  /// Una fila por línea del carrito: el mismo producto puede aparecer varias
  /// veces en la misma venta, por eso la clave es un id propio y no el producto.
  static const String _tablaCarrito = '''
    CREATE TABLE carrito (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      producto_id INTEGER NOT NULL,
      codigo TEXT,
      nombre TEXT,
      unidad TEXT,
      foto TEXT,
      precio_venta REAL,
      stock INTEGER,
      cantidad INTEGER,
      agregado TEXT
    )
  ''';

  static Future<Database> get instancia async {
    if (_db != null) return _db!;
    final ruta = p.join(await getDatabasesPath(), 'bean.db');
    _db = await openDatabase(
      ruta,
      version: 2,
      onConfigure: (db) => db.execute('PRAGMA foreign_keys = ON'),
      onUpgrade: (db, anterior, actual) async {
        // v2: el carrito pasa a tener una fila por línea (un mismo producto
        // puede agregarse varias veces en la misma venta).
        if (anterior < 2) {
          await db.execute('DROP TABLE IF EXISTS carrito');
          await db.execute(_tablaCarrito);
        }
      },
      onCreate: (db, version) async {
        final lote = db.batch();

        lote.execute('''
          CREATE TABLE ajustes (
            clave TEXT PRIMARY KEY,
            valor TEXT
          )
        ''');

        lote.execute('''
          CREATE TABLE sesion (
            id INTEGER PRIMARY KEY,
            token TEXT NOT NULL,
            user_id INTEGER,
            nombre TEXT,
            username TEXT,
            permisos TEXT,
            actualizado TEXT
          )
        ''');

        lote.execute('''
          CREATE TABLE productos (
            id INTEGER PRIMARY KEY,
            codigo TEXT,
            codigo_barras TEXT,
            nombre TEXT,
            categoria TEXT,
            unidad TEXT,
            precio_compra REAL,
            precio_venta REAL,
            stock INTEGER,
            foto TEXT
          )
        ''');
        lote.execute('CREATE INDEX idx_productos_nombre ON productos (nombre)');
        lote.execute('CREATE INDEX idx_productos_barras ON productos (codigo_barras)');

        lote.execute(_tablaCarrito);

        lote.execute('''
          CREATE TABLE ventas (
            id INTEGER PRIMARY KEY,
            numero TEXT,
            fecha TEXT,
            subtotal REAL,
            descuento REAL,
            total REAL,
            tipo_pago TEXT,
            monto_efectivo REAL,
            monto_qr REAL,
            estado TEXT,
            usuario_nombre TEXT,
            detalles TEXT
          )
        ''');
        lote.execute('CREATE INDEX idx_ventas_fecha ON ventas (fecha)');

        await lote.commit(noResult: true);
      },
    );
    return _db!;
  }

  // ── Ajustes ──────────────────────────────────────────────

  static Future<String?> ajuste(String clave) async {
    final db = await instancia;
    final filas = await db.query('ajustes', where: 'clave = ?', whereArgs: [clave], limit: 1);
    return filas.isEmpty ? null : filas.first['valor'] as String?;
  }

  static Future<void> guardarAjuste(String clave, String valor) async {
    final db = await instancia;
    await db.insert(
      'ajustes',
      {'clave': clave, 'valor': valor},
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }
}
