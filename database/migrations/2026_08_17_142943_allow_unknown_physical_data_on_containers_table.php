<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->unsignedBigInteger('container_type_id')->nullable()->change();
            $table->decimal('tare_weight_kg', 8, 2)->nullable()->change();
            $table->decimal('max_gross_weight_kg', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        $incomplete = DB::table('containers')
            ->whereNull('container_type_id')
            ->orWhereNull('tare_weight_kg')
            ->orWhereNull('max_gross_weight_kg')
            ->exists();

        if ($incomplete) {
            throw new \RuntimeException(
                'No se puede revertir: existen contenedores con datos físicos desconocidos.'
            );
        }

        Schema::table('containers', function (Blueprint $table) {
            $table->unsignedBigInteger('container_type_id')->nullable(false)->change();
            $table->decimal('tare_weight_kg', 8, 2)->nullable(false)->change();
            $table->decimal('max_gross_weight_kg', 8, 2)->nullable(false)->change();
        });
    }
};
