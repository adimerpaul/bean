<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VentaOfflineTest extends TestCase
{
    use RefreshDatabase;

    private function cajero(string ...$permisos): User
    {
        $user = User::create([
            'name' => 'CAJERO', 'username' => 'cajero'.uniqid(),
            'email' => uniqid().'@bean.test', 'password' => bcrypt('123456'),
        ]);
        $user->givePermissionTo($permisos ?: ['Crear Ventas', 'Crear Ventas Offline']);
        Sanctum::actingAs($user);

        return $user;
    }

    private function producto(float $stock = 10): Producto
    {
        $producto = Producto::firstOrFail();
        $producto->update(['stock_inicial' => $stock, 'precio_venta' => 10]);

        return $producto;
    }

    private function cuerpo(Producto $producto, string $uuid, float $cantidad = 2): array
    {
        return [
            'uuid' => $uuid,
            'fecha_offline' => now()->subHour()->toIso8601String(),
            'tipo_pago' => 'EFECTIVO',
            'detalles' => [['producto_id' => $producto->id, 'cantidad' => $cantidad, 'precio_venta' => 10]],
        ];
    }

    public function test_una_venta_offline_reenviada_no_se_duplica(): void
    {
        $this->cajero();
        $producto = $this->producto(10);
        $uuid = (string) Str::uuid();

        $primera = $this->postJson('/api/ventas', $this->cuerpo($producto, $uuid))->assertCreated();

        // Mismo uuid otra vez: el servidor devuelve la venta ya registrada.
        $this->postJson('/api/ventas', $this->cuerpo($producto, $uuid))
            ->assertOk()->assertJson(['duplicada' => true, 'id' => $primera->json('id')]);

        $this->assertSame(1, Venta::where('uuid', $uuid)->count());
        $this->assertEqualsWithDelta(8, (float) $producto->fresh()->stock_inicial, 0.001);
    }

    public function test_la_venta_offline_conserva_la_hora_del_cobro(): void
    {
        $this->cajero();
        $producto = $this->producto();

        $this->postJson('/api/ventas', $this->cuerpo($producto, (string) Str::uuid()))->assertCreated();

        $venta = Venta::latest('id')->first();
        $this->assertNotNull($venta->fecha_offline);
        $this->assertEqualsWithDelta(now()->subHour()->timestamp, $venta->fecha->timestamp, 5);
    }

    public function test_con_el_reloj_del_equipo_desfasado_se_fecha_con_la_hora_de_llegada(): void
    {
        $this->cajero();
        $cuerpo = $this->cuerpo($this->producto(), (string) Str::uuid());
        $cuerpo['fecha_offline'] = now()->addDays(3)->toIso8601String();

        $this->postJson('/api/ventas', $cuerpo)->assertCreated();

        $venta = Venta::latest('id')->first();
        $this->assertNotNull($venta->fecha_offline);
        $this->assertEqualsWithDelta(now()->timestamp, $venta->fecha->timestamp, 5);
    }

    public function test_una_venta_en_linea_con_uuid_no_queda_marcada_como_offline(): void
    {
        $this->cajero();
        $cuerpo = $this->cuerpo($this->producto(), (string) Str::uuid());
        unset($cuerpo['fecha_offline']);

        $this->postJson('/api/ventas', $cuerpo)->assertCreated()->assertJsonPath('fecha_offline', null);
    }

    public function test_la_verificacion_informa_los_uuid_ya_registrados(): void
    {
        $this->cajero();
        $producto = $this->producto();
        $enviado = (string) Str::uuid();
        $pendiente = (string) Str::uuid();
        $this->postJson('/api/ventas', $this->cuerpo($producto, $enviado))->assertCreated();

        $respuesta = $this->postJson('/api/ventas-offline/verificar', ['uuids' => [$enviado, $pendiente]])->assertOk();

        $this->assertArrayHasKey($enviado, $respuesta->json('registradas'));
        $this->assertArrayNotHasKey($pendiente, $respuesta->json('registradas'));
    }

    public function test_sin_stock_la_venta_offline_se_rechaza(): void
    {
        $this->cajero();
        $producto = $this->producto(1);

        $this->postJson('/api/ventas', $this->cuerpo($producto, (string) Str::uuid(), 3))->assertStatus(422);
        $this->assertSame(0, Venta::count());
    }

    public function test_el_permiso_offline_alcanza_para_enviar_la_cola_pero_no_para_vender_en_linea(): void
    {
        $this->cajero('Crear Ventas Offline');
        $producto = $this->producto();
        $cuerpo = $this->cuerpo($producto, (string) Str::uuid());

        $this->postJson('/api/ventas', $cuerpo)->assertCreated();

        unset($cuerpo['uuid'], $cuerpo['fecha_offline']);
        $this->postJson('/api/ventas', $cuerpo)->assertForbidden();
    }
}
