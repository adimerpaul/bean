import 'package:intl/intl.dart';

const String kMoneda = 'Bs';

final _numero = NumberFormat('#,##0.00', 'es');
final _fechaHora = DateFormat('dd/MM/yyyy HH:mm');
final _fecha = DateFormat('dd/MM/yyyy');

String dinero(num valor) => '$kMoneda ${_numero.format(valor)}';

String numero(num valor) => _numero.format(valor);

String fechaHora(DateTime? valor) => valor == null ? '' : _fechaHora.format(valor.toLocal());

String fecha(DateTime? valor) => valor == null ? '' : _fecha.format(valor.toLocal());

String fechaApi(DateTime valor) => DateFormat('yyyy-MM-dd').format(valor);

double aDouble(dynamic valor) {
  if (valor == null) return 0;
  if (valor is num) return valor.toDouble();
  return double.tryParse(valor.toString()) ?? 0;
}

int aEntero(dynamic valor) {
  if (valor == null) return 0;
  if (valor is num) return valor.round();
  return int.tryParse(valor.toString()) ?? aDouble(valor).round();
}
