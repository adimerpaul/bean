import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/entorno.dart';
import 'core/tema.dart';
import 'datos/repositorios/carrito_repositorio.dart';
import 'datos/repositorios/producto_repositorio.dart';
import 'datos/repositorios/sesion_repositorio.dart';
import 'datos/repositorios/venta_repositorio.dart';
import 'datos/servicios/api_servicio.dart';
import 'datos/servicios/sonido_servicio.dart';
import 'vistamodelos/productos_vistamodelo.dart';
import 'vistamodelos/sesion_vistamodelo.dart';
import 'vistamodelos/venta_vistamodelo.dart';
import 'vistamodelos/ventas_vistamodelo.dart';
import 'vistas/login/login_vista.dart';
import 'vistas/principal/principal_vista.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Entorno.cargar();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  runApp(const AplicacionBean());
}

/// Composición de dependencias: servicios → repositorios → ViewModels → vistas.
class AplicacionBean extends StatelessWidget {
  const AplicacionBean({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<ApiServicio>(create: (_) => ApiServicio()),
        Provider<CarritoRepositorio>(create: (_) => CarritoRepositorio()),
        Provider<SonidoServicio>(
          create: (_) => SonidoServicio(),
          dispose: (_, sonidos) => sonidos.dispose(),
        ),
        ProxyProvider<ApiServicio, SesionRepositorio>(
          update: (_, api, _) => SesionRepositorio(api),
        ),
        ProxyProvider<ApiServicio, ProductoRepositorio>(
          update: (_, api, _) => ProductoRepositorio(api),
        ),
        ProxyProvider<ApiServicio, VentaRepositorio>(
          update: (_, api, _) => VentaRepositorio(api),
        ),
        ChangeNotifierProvider<SesionVistaModelo>(
          create: (contexto) => SesionVistaModelo(
            contexto.read<SesionRepositorio>(),
            contexto.read<ApiServicio>(),
          )..iniciar(),
        ),
        ChangeNotifierProvider<VentaVistaModelo>(
          create: (contexto) => VentaVistaModelo(
            contexto.read<CarritoRepositorio>(),
            contexto.read<ProductoRepositorio>(),
          ),
        ),
        ChangeNotifierProvider<VentasVistaModelo>(
          create: (contexto) => VentasVistaModelo(contexto.read<VentaRepositorio>()),
        ),
        ChangeNotifierProvider<ProductosVistaModelo>(
          create: (contexto) => ProductosVistaModelo(contexto.read<ProductoRepositorio>()),
        ),
      ],
      child: MaterialApp(
        title: 'Bean Ventas',
        debugShowCheckedModeBanner: false,
        theme: construirTema(),
        locale: const Locale('es'),
        supportedLocales: const [Locale('es'), Locale('en')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: const _Enrutador(),
      ),
    );
  }
}

/// Decide qué pantalla mostrar según el estado de la sesión guardada.
class _Enrutador extends StatelessWidget {
  const _Enrutador();

  @override
  Widget build(BuildContext context) {
    final estado = context.select<SesionVistaModelo, EstadoSesion>((vm) => vm.estado);

    return switch (estado) {
      EstadoSesion.iniciando => const _Cargando(),
      EstadoSesion.invitado => const LoginVista(),
      EstadoSesion.autenticado => const _SesionIniciada(),
    };
  }
}

/// Carga el carrito y el catálogo guardados antes de mostrar la app.
class _SesionIniciada extends StatefulWidget {
  const _SesionIniciada();

  @override
  State<_SesionIniciada> createState() => _SesionIniciadaState();
}

class _SesionIniciadaState extends State<_SesionIniciada> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<VentaVistaModelo>().iniciar();
    });
  }

  @override
  Widget build(BuildContext context) => const PrincipalVista();
}

class _Cargando extends StatelessWidget {
  const _Cargando();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColores.negro,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(28),
              child: Image.asset('assets/icono/logo.png', height: 132, width: 132),
            ),
            const SizedBox(height: 22),
            const SizedBox(
              height: 26,
              width: 26,
              child: CircularProgressIndicator(strokeWidth: 2.6, color: AppColores.primario),
            ),
          ],
        ),
      ),
    );
  }
}
