<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Se crea después de add_group_to_permissions, así que fija su grupo para no caer en "Otros".
        $permission = Permission::firstOrCreate(
            ['name' => 'Editar Stock Inicial', 'guard_name' => 'web'],
            ['grupo' => 'Productos', 'orden' => 3]
        );
        User::where('username', 'admin')->first()?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::where('name', 'Editar Stock Inicial')->delete();
    }
};
