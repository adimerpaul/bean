<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GananciaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function sell(Producto $product, float $quantity, float $price, $date, float $discount = 0): int
    {
        $id = $this->postJson('/api/ventas', [
            'tipo_pago' => 'EFECTIVO', 'descuento' => $discount,
            'detalles' => [['producto_id' => $product->id, 'cantidad' => $quantity, 'precio_venta' => $price]],
        ])->assertCreated()->json('id');
        Venta::whereKey($id)->update(['fecha' => $date]);

        return $id;
    }

    private function products(): array
    {
        [$a, $b] = Producto::orderBy('id')->take(2)->get()->all();
        $a->update(['stock_inicial' => 100, 'precio_compra' => 6, 'precio_venta' => 10]);
        $b->update(['stock_inicial' => 100, 'precio_compra' => 2, 'precio_venta' => 3]);

        return [$a->fresh(), $b->fresh()];
    }

    public function test_profit_defaults_to_current_month_and_ranks_products(): void
    {
        $this->admin();
        [$a, $b] = $this->products();
        $this->sell($a, 2, 10, now()->startOfMonth()->setTime(10, 0));        // ganancia 8
        $this->sell($b, 4, 3, now()->startOfMonth()->setTime(11, 0), 1);       // ganancia 4 - 1 = 3
        $this->sell($a, 5, 10, now()->startOfMonth()->subDays(3));             // mes anterior: 20

        $response = $this->getJson('/api/ganancias')->assertOk();

        $this->assertSame(11.0, (float) $response->json('indicadores.ganancia'));
        $this->assertSame(31.0, (float) $response->json('indicadores.ventas'));
        $this->assertSame(20.0, (float) $response->json('indicadores.costo'));
        $this->assertSame(2, $response->json('indicadores.cantidad_ventas'));
        $this->assertSame($a->id, $response->json('productos.0.producto_id'));
        $this->assertSame(8.0, (float) $response->json('productos.0.ganancia'));
        $this->assertSame(11.0, (float) collect($response->json('serie'))->sum('ganancia'));

        // Diez días: serie diaria y comparación contra los diez días previos.
        $start = now()->startOfMonth();
        $range = $this->getJson('/api/ganancias?desde='.$start->toDateString().'&hasta='.$start->copy()->addDays(9)->toDateString())->assertOk();
        $range->assertJsonPath('periodo.granularidad', 'dia');
        $this->assertCount(10, $range->json('serie'));
        $this->assertSame(20.0, (float) $range->json('anterior.ganancia'));
    }

    public function test_filters_and_cancelled_sales(): void
    {
        $this->admin();
        [$a, $b] = $this->products();
        $this->sell($a, 2, 10, now());
        $this->sell($b, 4, 3, now());
        $cancelled = $this->sell($a, 3, 10, now());
        $this->putJson("/api/ventas/$cancelled/anular")->assertOk();

        $byProduct = $this->getJson('/api/ganancias?productos[]='.$b->id)->assertOk();
        $this->assertSame(4.0, (float) $byProduct->json('indicadores.ganancia'));

        $byCategory = $this->getJson('/api/ganancias?categorias[]='.$a->categoria_id)->assertOk();
        $this->assertSame((float) collect($byCategory->json('productos'))->sum('ganancia'), (float) $byCategory->json('indicadores.ganancia'));
        $this->assertContains($a->id, collect($byCategory->json('productos'))->pluck('producto_id')->all());

        $this->assertSame(12.0, (float) $this->getJson('/api/ganancias')->json('indicadores.ganancia'));
    }

    public function test_sales_behind_a_product(): void
    {
        $this->admin();
        [$a, $b] = $this->products();
        $this->sell($a, 2, 10, now()->setTime(9, 0));
        $this->sell($a, 1, 10, now()->setTime(12, 0));
        $this->sell($b, 1, 3, now());

        $response = $this->getJson('/api/ganancias-ventas?producto_id='.$a->id)->assertOk();
        $this->assertSame(2, $response->json('lineas.total'));
        $this->assertSame(12.0, (float) $response->json('resumen.ganancia'));
        $this->assertSame(4.0, (float) $response->json('lineas.data.0.ganancia'));

        // Un tramo del gráfico llega con hora: sólo entra lo vendido en ese intervalo.
        $hour = $this->getJson('/api/ganancias-ventas?desde='.now()->setTime(9, 0)->format('Y-m-d H:i:s').'&hasta='.now()->setTime(9, 59, 59)->format('Y-m-d H:i:s'))->assertOk();
        $this->assertSame(1, $hour->json('lineas.total'));
    }

    public function test_requires_permission(): void
    {
        Sanctum::actingAs(User::create(['name' => 'CAJERO', 'username' => 'cajero', 'password' => bcrypt('123456')]));
        $this->getJson('/api/ganancias')->assertForbidden();
        $this->getJson('/api/ganancias-ventas')->assertForbidden();
        $this->getJson('/api/ganancias-catalogos')->assertForbidden();
    }
}
