<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('cargo_capacity_tons', 10, 2)
                ->nullable()
                ->comment('Capacidad carga en toneladas')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('cargo_capacity_tons', 10, 2)
                ->nullable(false)
                ->comment('Capacidad carga en toneladas')
                ->change();
        });
    }
};
