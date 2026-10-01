import 'package:bean/vistas/widgets/comunes.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// El carrito vacío se dibuja dentro de un `Expanded` que puede quedar muy bajo
/// cuando el escáner está abierto: ahí aparecía el desborde de píxeles.
void main() {
  Widget envolver(double alto) => MaterialApp(
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: 360,
              height: alto,
              child: const EstadoVacio(
                icono: Icons.shopping_basket_outlined,
                titulo: 'Carrito vacío',
                detalle:
                    'Busque un producto o escanee su código de barras para empezar la venta.',
              ),
            ),
          ),
        ),
      );

  for (final alto in [60.0, 100.0, 150.0, 220.0, 400.0]) {
    testWidgets('no desborda con $alto px de alto', (tester) async {
      await tester.pumpWidget(envolver(alto));
      expect(tester.takeException(), isNull);
      expect(find.text('Carrito vacío'), findsOneWidget);
    });
  }

  testWidgets('con acción tampoco desborda en espacio reducido', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Center(
            child: SizedBox(
              width: 360,
              height: 130,
              child: EstadoVacio(
                icono: Icons.inventory_2_outlined,
                titulo: 'Catálogo vacío',
                detalle: 'Sincronice para descargar los productos del servidor.',
                accion: ElevatedButton(onPressed: () {}, child: const Text('Sincronizar')),
              ),
            ),
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
  });
}
