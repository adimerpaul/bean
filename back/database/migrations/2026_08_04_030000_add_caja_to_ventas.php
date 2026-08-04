<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Caja 1 a 5; las ventas anteriores quedan en la caja 1.
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedTinyInteger('caja')->default(1)->after('usuario_nombre')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['caja']);
            $table->dropColumn('caja');
        });
    }
};
