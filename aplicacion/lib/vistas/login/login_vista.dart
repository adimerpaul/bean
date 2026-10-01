import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/tema.dart';
import '../../vistamodelos/sesion_vistamodelo.dart';
import '../widgets/comunes.dart';

class LoginVista extends StatefulWidget {
  const LoginVista({super.key});

  @override
  State<LoginVista> createState() => _LoginVistaState();
}

class _LoginVistaState extends State<LoginVista> {
  final _formulario = GlobalKey<FormState>();
  final _usuario = TextEditingController();
  final _contrasena = TextEditingController();
  bool _oculta = true;

  @override
  void dispose() {
    _usuario.dispose();
    _contrasena.dispose();
    super.dispose();
  }

  Future<void> _entrar() async {
    if (!_formulario.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final vm = context.read<SesionVistaModelo>();
    final ok = await vm.iniciarSesion(_usuario.text, _contrasena.text);
    if (!mounted) return;
    if (!ok) mostrarMensaje(context, vm.error ?? 'No se pudo iniciar sesión', esError: true);
  }

  Future<void> _configurarServidor() async {
    final vm = context.read<SesionVistaModelo>();
    final control = TextEditingController(text: vm.urlApi);

    final url = await showDialog<String>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Servidor'),
        content: TextField(
          controller: control,
          autofocus: true,
          keyboardType: TextInputType.url,
          decoration: const InputDecoration(
            labelText: 'URL de la API',
            hintText: 'https://midominio.com/api',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto), child: const Text('Cancelar')),
          TextButton(
            onPressed: () => Navigator.pop(contexto, control.text.trim()),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );

    if (url != null && url.isNotEmpty) {
      await vm.guardarUrlApi(url);
      if (mounted) mostrarMensaje(context, 'Servidor actualizado');
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<SesionVistaModelo>();

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formulario,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Center(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(26),
                        child: Image.asset(
                          'assets/icono/logo.png',
                          height: 128,
                          width: 128,
                          filterQuality: FilterQuality.medium,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'Ventas móviles de Bean',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColores.textoSuave, fontSize: 15),
                    ),
                    const SizedBox(height: 30),
                    TextFormField(
                      controller: _usuario,
                      textInputAction: TextInputAction.next,
                      autocorrect: false,
                      decoration: const InputDecoration(
                        labelText: 'Usuario',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                      validator: (valor) =>
                          (valor == null || valor.trim().isEmpty) ? 'Ingrese su usuario' : null,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _contrasena,
                      obscureText: _oculta,
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _entrar(),
                      decoration: InputDecoration(
                        labelText: 'Contraseña',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          icon: Icon(_oculta ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          onPressed: () => setState(() => _oculta = !_oculta),
                        ),
                      ),
                      validator: (valor) =>
                          (valor == null || valor.isEmpty) ? 'Ingrese su contraseña' : null,
                    ),
                    const SizedBox(height: 26),
                    ElevatedButton(
                      onPressed: vm.cargando ? null : _entrar,
                      child: vm.cargando
                          ? const SizedBox(
                              height: 22,
                              width: 22,
                              child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                            )
                          : const Text('Ingresar'),
                    ),
                    const SizedBox(height: 12),
                    TextButton.icon(
                      onPressed: _configurarServidor,
                      icon: const Icon(Icons.dns_outlined, size: 18),
                      label: Text(
                        vm.urlApi,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
