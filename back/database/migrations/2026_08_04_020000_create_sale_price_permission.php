<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'Modificar Precio en Venta', 'guard_name' => 'web'],
            ['grupo' => 'Ventas', 'orden' => 4]
        );
        $permission->update(['grupo' => 'Ventas', 'orden' => 4]);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'Modificar Precio en Venta')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
