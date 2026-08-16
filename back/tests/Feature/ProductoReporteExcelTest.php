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

class ProductoReporteExcelTest extends TestCase
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
        $response = $this->get('/api/productos-exportar/excel?'.http_build_query($params));
        $response->assertOk();

        $archivo = tempnam(sys_get_temp_dir(), 'bean').'.xlsx';
        file_put_contents($archivo, $response->streamedContent());
        $libro = IOFactory::load($archivo);
        @unlink($archivo);

        return $libro;
    }

    public function test_report_has_one_sheet_per_topic_with_title_date_and_user(): void
    {
        $admin = $this->admin();
        $libro = $this->hojas();

        $this->assertSame(
            ['Resumen', 'Inventario', 'Compras por producto', 'Ventas por producto'],
            $libro->getSheetNames()
        );

        $hoja = $libro->getSheetByName('Inventario');
        $this->assertSame('Bean', $hoja->getCell('A1')->getValue());
        $this->assertStringContainsString('INVENTARIO DE PRODUCTOS', $hoja->getCell('A2')->getValue());
        $this->assertStringContainsString($admin->name, $hoja->getCell('A3')->getValue());
        $this->assertStringContainsString(now()->format('d/m/Y'), $hoja->getCell('A3')->getValue());
        $this->assertStringContainsString('Periodo:', $hoja->getCell('A4')->getValue());
        $this->assertSame('Código', $hoja->getCell('B6')->getValue());
        $this->assertNotNull($hoja->getAutoFilter()->getRange());
    }

    public function test_purchase_and_sale_sheets_aggregate_by_product(): void
    {
        $this->admin();
        $producto = Producto::first();
        $producto->update(['precio_venta' => 20, 'stock_inicial' => 0]);
        $proveedor = Proveedor::create(['nombre' => 'PROVEEDOR PRUEBA']);

        $this->postJson('/api/compras', [
            'proveedor_id' => $proveedor->id, 'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 10, 'precio_unitario' => 12]],
        ])->assertCreated();

        $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 4, 'precio_venta' => 20]],
        ])->assertCreated();

        $libro = $this->hojas();

        $compras = $libro->getSheetByName('Compras por producto');
        $this->assertSame($producto->codigo, $compras->getCell('A7')->getValue());
        $this->assertEqualsWithDelta(10, $compras->getCell('F7')->getValue(), 0.001);
        $this->assertEqualsWithDelta(120, $compras->getCell('G7')->getValue(), 0.01);

        $ventas = $libro->getSheetByName('Ventas por producto');
        $this->assertSame($producto->codigo, $ventas->getCell('A7')->getValue());
        $this->assertEqualsWithDelta(4, $ventas->getCell('F7')->getValue(), 0.001);
        $this->assertEqualsWithDelta(80, $ventas->getCell('I7')->getValue(), 0.01);
        // Costo = 4 × precio_compra del snapshot (12, actualizado por la compra); ganancia = 80 - 48.
        $this->assertEqualsWithDelta(48, $ventas->getCell('J7')->getValue(), 0.01);
        $this->assertEqualsWithDelta(32, $ventas->getCell('K7')->getValue(), 0.01);
    }

    public function test_date_range_filters_the_movement_sheets(): void
    {
        $this->admin();
        $producto = Producto::first();
        $producto->update(['precio_venta' => 20, 'stock_inicial' => 50]);

        $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => 2, 'precio_venta' => 20]],
        ])->assertCreated();

        $hoja = $this->hojas([
            'desde' => now()->subDays(10)->toDateString(),
            'hasta' => now()->subDays(5)->toDateString(),
        ])->getSheetByName('Ventas por producto');

        $this->assertStringContainsString('No hay ventas registradas', $hoja->getCell('A7')->getValue());
    }

    public function test_export_requires_the_view_products_permission(): void
    {
        $usuario = User::create([
            'name' => 'SIN PERMISOS', 'username' => 'sinpermisos',
            'email' => 'sin@bean.bo', 'ci' => '11111111', 'password' => bcrypt('x'),
        ]);
        Sanctum::actingAs($usuario);

        $this->get('/api/productos-exportar/excel')->assertForbidden();
    }
}
