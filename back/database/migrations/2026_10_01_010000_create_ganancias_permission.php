<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private string $permiso = 'Ver Ganancias';

    /**
     * Control de ganancias: pantalla propia, separada del panel de inicio, porque
     * muestra costos de compra y márgenes por producto que no todos deben ver.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => $this->permiso, 'guard_name' => 'web'],
            ['grupo' => 'Ganancias', 'orden' => 8]
        );
        $permission->update(['grupo' => 'Ganancias', 'orden' => 8]);
        DB::table(config('permission.table_names.permissions'))
            ->where('name', 'Gestionar Configuración')->update(['orden' => 9]);
        User::where('username', 'admin')->first()?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', $this->permiso)->delete();
        DB::table(config('permission.table_names.permissions'))
            ->where('name', 'Gestionar Configuración')->update(['orden' => 8]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
