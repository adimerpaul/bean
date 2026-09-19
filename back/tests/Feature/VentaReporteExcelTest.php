<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class VentaReporteExcelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** Descarga el reporte y lo vuelve a abrir para inspeccionar las hojas. */
    private function hojas(array $params = []): Spreadsheet
    {
        $response = $this->get('/api/ventas-exportar/excel?'.http_build_query($params));
        $response->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'bean').'.xlsx';
        file_put_contents($archivo, $response->streamedContent());
        $libro = IOFactory::load($archivo);
        @unlink($archivo);

        return $libro;
    }

    /** Compra que deja un lote con vencimiento y venta de parte de ese lote. */
    private function comprarYVender(Producto $producto, float $cantidad = 4): array
    {
        $proveedor = Proveedor::create(['nombre' => 'PROVEEDOR PRUEBA']);
        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id, 'tipo_pago' => 'EFECTIVO',
            'detalles' => [[
                'producto_id' => $producto->id, 'cantidad' => 10, 'precio_unitario' => 12,
                'lote' => 'L-001', 'fecha_vencimiento' => now()->addDays(10)->toDateString(),
            ]],
        ])->assertCreated();

        return $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio_venta' => 20]],
        ])->assertCreated()->json();
    }

    public function test_report_has_one_sheet_per_topic_with_title_date_and_user(): void
    {
        $admin = $this->admin();
        $libro = $this->hojas();

        $this->assertSame(
            ['Resumen', 'Ventas', 'Detalle', 'Por producto', 'Por usuario', 'Por día', 'Por hora', 'Lotes y vencimientos'],
            $libro->getSheetNames()
        );

        $hoja = $libro->getSheetByName('Ventas');
        $this->assertSame('Bean', $hoja->getCell('A1')->getValue());
        $this->assertStringContainsString('DOCUMENTOS DE VENTA', $hoja->getCell('A2')->getValue());
        $this->assertStringContainsString($admin->name, $hoja->getCell('A3')->getValue());
        $this->assertStringContainsString(now()->format('d/m/Y'), $hoja->getCell('A3')->getValue());
        $this->assertStringContainsString('Periodo:', $hoja->getCell('A4')->getValue());
        $this->assertSame('Nº venta', $hoja->getCell('B6')->getValue());
        $this->assertNotNull($hoja->getAutoFilter()->getRange());
    }

    public function test_sold_sale_appears_in_every_sheet_with_its_user_product_and_lot(): void
    {
        $admin = $this->admin();
        $producto = Producto::first();
        $producto->update(['precio_venta' => 20, 'stock_inicial' => 0]);
        $venta = $this->comprarYVender($producto);

        $libro = $this->hojas();

        $ventas = $libro->getSheetByName('Ventas');
        $this->assertSame($venta['numero'], $ventas->getCell('B7')->getValue());
        $this->assertSame($admin->name, $ventas->getCell('F7')->getValue());
        $this->assertEqualsWithDelta(80, $ventas->getCell('M7')->getValue(), 0.01);   // Total
        $this->assertEqualsWithDelta(48, $ventas->getCell('P7')->getValue(), 0.01);   // Costo 4 × 12
        $this->assertEqualsWithDelta(32, $ventas->getCell('Q7')->getValue(), 0.01);   // Ganancia
        $this->assertSame('COMPLETADA', $ventas->getCell('R7')->getValue());

        $producto7 = $libro->getSheetByName('Por producto');
        $this->assertSame($producto->codigo, $producto7->getCell('B7')->getValue());
        $this->assertEqualsWithDelta(4, $producto7->getCell('G7')->getValue(), 0.001);
        $this->assertEqualsWithDelta(80, $producto7->getCell('J7')->getValue(), 0.01);

        $usuarios = $libro->getSheetByName('Por usuario');
        $this->assertSame($admin->name, $usuarios->getCell('B7')->getValue());
        $this->assertEqualsWithDelta(80, $usuarios->getCell('H7')->getValue(), 0.01);

        // El lote consumido se reporta con su vencimiento y los días de margen con los que salió.
        $lotes = $libro->getSheetByName('Lotes y vencimientos');
        $this->assertSame('L-001', $lotes->getCell('H7')->getValue());
        $this->assertSame(10, $lotes->getCell('J7')->getValue());
        $this->assertSame('POR VENCER (30 DÍAS)', $lotes->getCell('K7')->getValue());
        $this->assertEqualsWithDelta(4, $lotes->getCell('L7')->getValue(), 0.001);
    }

    public function test_cancelled_sales_are_listed_apart_and_do_not_add_to_the_totals(): void
    {
        $this->admin();
        $producto = Producto::first();
        $producto->update(['precio_venta' => 20, 'stock_inicial' => 0]);
        $vendida = $this->comprarYVender($producto, 2);
        $anulada = $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 3, 'precio_venta' => 20]],
        ])->assertCreated()->json();
        $this->putJson("/api/ventas/{$anulada['id']}/anular")->assertOk();

        $libro = $this->hojas();

        // Resumen: una sola línea (misma caja y forma de pago) con lo anulado en columnas propias.
        $resumen = $libro->getSheetByName('Resumen');
        $this->assertSame(1, $resumen->getCell('C7')->getValue());
        $this->assertEqualsWithDelta(40, $resumen->getCell('G7')->getValue(), 0.01);
        $this->assertSame(1, $resumen->getCell('N7')->getValue());
        $this->assertEqualsWithDelta(60, $resumen->getCell('O7')->getValue(), 0.01);

        // Hoja de documentos: ambas aparecen, el total sólo cuenta la completada.
        $ventas = $libro->getSheetByName('Ventas');
        $estados = [$ventas->getCell('R7')->getValue(), $ventas->getCell('R8')->getValue()];
        $this->assertSame(['COMPLETADA', 'ANULADA'], $estados);
        $this->assertSame($vendida['numero'], $ventas->getCell('B7')->getValue());
        $this->assertStringContainsString('sólo completadas', $ventas->getCell('A9')->getValue());
        $this->assertEqualsWithDelta(40, $ventas->getCell('M9')->getValue(), 0.01);

        // Por producto: 2 vendidas y 3 anuladas en la misma fila.
        $porProducto = $libro->getSheetByName('Por producto');
        $this->assertEqualsWithDelta(2, $porProducto->getCell('G7')->getValue(), 0.001);
        $this->assertEqualsWithDelta(3, $porProducto->getCell('R7')->getValue(), 0.001);
        $this->assertEqualsWithDelta(60, $porProducto->getCell('S7')->getValue(), 0.01);
    }

    public function test_the_filters_of_the_screen_apply_to_the_report(): void
    {
        $this->admin();
        $producto = Producto::first();
        $producto->update(['precio_venta' => 20, 'stock_inicial' => 50]);
        $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 2, 'precio_venta' => 20]],
        ])->assertCreated();

        $libro = $this->hojas([
            'desde' => now()->subDays(10)->toDateString(),
            'hasta' => now()->subDays(5)->toDateString(),
            'caja' => 3,
        ]);

        $this->assertStringContainsString('No hay ventas registradas', $libro->getSheetByName('Ventas')->getCell('A7')->getValue());
        $this->assertStringContainsString('CAJA 3', $libro->getSheetByName('Ventas')->getCell('A4')->getValue());
        $this->assertStringContainsString('No se vendió ningún producto', $libro->getSheetByName('Detalle')->getCell('A7')->getValue());
    }

    public function test_export_requires_the_view_sales_permission(): void
    {
        $usuario = User::create([
            'name' => 'SIN PERMISOS', 'username' => 'sinventas',
            'email' => 'sinventas@bean.bo', 'ci' => '22222222', 'password' => bcrypt('x'),
        ]);
        Sanctum::actingAs($usuario);

        $this->get('/api/ventas-exportar/excel')->assertForbidden();
    }
}
