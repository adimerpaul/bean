<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlmacenExportTest extends TestCase
{
    use RefreshDatabase;

    private function almacenConteo(): array
    {
        Sanctum::actingAs(User::where('username', 'admin')->firstOrFail());
        $almacen = $this->postJson('/api/almacenes', ['descripcion' => 'REVISIÓN DE PRUEBA'])->assertCreated()->json();
        $this->postJson("/api/almacenes/{$almacen['id']}/detalles", [
            'producto_id' => Producto::first()->id,
            'conteos' => [['lote' => 'L-1', 'fecha_vencimiento' => '2026-12-01', 'cantidad' => 7]],
        ])->assertCreated();

        return $almacen;
    }

    private function assertExcel(string $url): void
    {
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertGreaterThan(4000, strlen($response->streamedContent()));
    }

    private function assertPdf(string $url): void
    {
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_three_almacen_screens_export_excel(): void
    {
        $almacen = $this->almacenConteo();

        $this->assertExcel('/api/almacenes-exportar/excel');
        $this->assertExcel("/api/almacenes/{$almacen['id']}/exportar/excel");
        $this->assertExcel("/api/almacenes/{$almacen['id']}/avance-exportar/excel");
    }

    public function test_avance_offers_capital_and_pdf_reports(): void
    {
        $almacen = $this->almacenConteo();

        $this->assertExcel("/api/almacenes/{$almacen['id']}/capital-exportar/excel");
        $this->assertPdf("/api/almacenes/{$almacen['id']}/avance-exportar/pdf");
        $this->assertPdf("/api/almacenes/{$almacen['id']}/capital-exportar/pdf");
    }

    public function test_export_requires_the_view_almacenes_permission(): void
    {
        $almacen = $this->almacenConteo();
        Sanctum::actingAs(User::create(['name' => 'SIN PERMISOS', 'username' => 'nadie', 'password' => bcrypt('123456')]));

        $this->get('/api/almacenes-exportar/excel')->assertForbidden();
        $this->get("/api/almacenes/{$almacen['id']}/exportar/excel")->assertForbidden();
        $this->get("/api/almacenes/{$almacen['id']}/avance-exportar/excel")->assertForbidden();
        $this->get("/api/almacenes/{$almacen['id']}/capital-exportar/excel")->assertForbidden();
        $this->get("/api/almacenes/{$almacen['id']}/avance-exportar/pdf")->assertForbidden();
        $this->get("/api/almacenes/{$almacen['id']}/capital-exportar/pdf")->assertForbidden();
    }
}
