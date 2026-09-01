<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
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

        \App\Models\Venta::whereKey($id)->update(['fecha' => $date]);
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
}
