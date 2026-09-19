<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** Registra una venta y le mueve la fecha, que es lo que agrupa el panel. */
    private function sell(Producto $product, float $quantity, $date): void
    {
        $id = $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $product->id, 'cantidad' => $quantity, 'precio_venta' => 10]],
        ])->assertCreated()->json('id');

        Venta::whereKey($id)->update(['fecha' => $date]);
    }

    public function test_dashboard_defaults_to_the_week_and_fills_empty_days(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 500]);
        $this->sell($product, 2, now()->subDays(2)->setTime(10, 0));

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('periodo.clave', 'semana')->assertJsonPath('periodo.granularidad', 'dia');
        $this->assertCount(7, $response->json('diario'));
        $this->assertSame(20.0, (float) $response->json('indicadores.ventas'));
        $this->assertSame(20.0, (float) collect($response->json('diario'))->sum('total'));
        $this->assertSame(1, (int) collect($response->json('diario'))->sum('cantidad'));
    }

    public function test_each_period_uses_its_own_range_and_granularity(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 500]);
        $this->sell($product, 1, now()->setTime(9, 0));
        $this->sell($product, 3, now()->subDay()->setTime(9, 0));
        $this->sell($product, 5, now()->subMonths(3)->setTime(9, 0));

        $today = $this->getJson('/api/dashboard?periodo=hoy')->assertOk();
        $today->assertJsonPath('periodo.granularidad', 'hora');
        $this->assertCount(24, $today->json('diario'));
        $this->assertSame(10.0, (float) $today->json('indicadores.ventas'));

        $yesterday = $this->getJson('/api/dashboard?periodo=ayer')->assertOk();
        $this->assertSame(30.0, (float) $yesterday->json('indicadores.ventas'));

        // La venta de hace tres meses sólo entra en el año.
        $this->assertSame(40.0, (float) $this->getJson('/api/dashboard?periodo=mes')->json('indicadores.ventas'));
        $year = $this->getJson('/api/dashboard?periodo=anio')->assertOk();
        $year->assertJsonPath('periodo.granularidad', 'mes');
        $this->assertCount(12, $year->json('diario'));
        $this->assertSame(90.0, (float) $year->json('indicadores.ventas'));
    }

    public function test_custom_range_limits_the_panel_to_the_chosen_dates(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 500]);
        $this->sell($product, 2, now()->subDays(10)->setTime(9, 0));
        $this->sell($product, 4, now()->subDays(3)->setTime(9, 0));

        $from = now()->subDays(11)->toDateString();
        $to = now()->subDays(9)->toDateString();
        $range = $this->getJson("/api/dashboard?periodo=rango&desde=$from&hasta=$to")->assertOk();

        $range->assertJsonPath('periodo.clave', 'rango')->assertJsonPath('periodo.granularidad', 'dia');
        $this->assertCount(3, $range->json('diario'));
        $this->assertSame(20.0, (float) $range->json('indicadores.ventas'));

        // Fechas al revés: se ordenan solas en lugar de devolver un rango vacío.
        $inverted = $this->getJson("/api/dashboard?periodo=rango&desde=$to&hasta=$from")->assertOk();
        $this->assertSame(20.0, (float) $inverted->json('indicadores.ventas'));
    }

    public function test_top_lists_group_by_product_and_by_category_with_profit(): void
    {
        $this->admin();
        $product = Producto::first();
        $product->update(['stock_inicial' => 500, 'precio_compra' => 4]);
        $this->sell($product, 3, now()->setTime(9, 0));

        $response = $this->getJson('/api/dashboard?periodo=hoy')->assertOk();

        $response->assertJsonPath('productos_top.0.nombre', $product->nombre);
        $this->assertSame(18.0, (float) $response->json('productos_top.0.ganancia'));
        $this->assertCount(1, $response->json('categorias_top'));
        $this->assertSame(18.0, (float) $response->json('categorias_top.0.ganancia'));
        $this->assertSame(30.0, (float) $response->json('categorias_top.0.total'));
    }
}
