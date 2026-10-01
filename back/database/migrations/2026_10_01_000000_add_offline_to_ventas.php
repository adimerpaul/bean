<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permiso = 'Crear Ventas Offline';

    /**
     * Las ventas cobradas sin conexión se guardan en el navegador y se envían después.
     * El uuid lo genera el navegador al cobrar y es único en la tabla: si el envío se
     * repite (se cortó la respuesta, se tocó dos veces "Exportar", dos pestañas), la
     * venta no se duplica y la venta offline queda vinculada a la venta registrada.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('numero');
            $table->timestamp('fecha_offline')->nullable()->after('fecha');
        });

        $permission = Permission::firstOrCreate(
            ['name' => $this->permiso, 'guard_name' => 'web'],
            ['grupo' => 'Ventas', 'orden' => 5]
        );
        $permission->update(['grupo' => 'Ventas', 'orden' => 5]);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'fecha_offline']);
        });

        Permission::where('name', $this->permiso)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
