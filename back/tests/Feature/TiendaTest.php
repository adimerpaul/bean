<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_is_public_and_answers_with_the_whatsapp_number(): void
    {
        $this->getJson('/api/tienda')
            ->assertOk()
            ->assertJsonPath('whatsapp', '59165417608')
            ->assertJsonStructure(['empresa' => ['nombre', 'direccion', 'telefono', 'logo'], 'categorias', 'total_productos']);
    }

    public function test_the_showcase_hides_cost_and_stock_but_says_if_there_is_availability(): void
    {
        $agotado = Producto::first();
        $agotado->update(['stock_inicial' => 0, 'precio_venta' => 25]);

        $producto = $this->getJson("/api/tienda/productos?q={$agotado->codigo}")
            ->assertOk()->assertJsonPath('data.0.disponible', false)
            ->json('data.0');

        $this->assertSame(['id', 'codigo', 'nombre', 'categoria', 'unidad', 'precio_venta', 'foto', 'disponible'], array_keys($producto));
    }

    public function test_products_without_a_sale_price_are_not_published(): void
    {
        $oculto = Producto::first();
        $oculto->update(['precio_venta' => 0]);

        $this->getJson("/api/tienda/productos?q={$oculto->codigo}")->assertOk()->assertJsonCount(0, 'data');
    }
}
