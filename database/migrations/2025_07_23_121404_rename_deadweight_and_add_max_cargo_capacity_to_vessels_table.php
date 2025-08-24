<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CORRECCIÓN: Convertida a clase anónima para consistencia
     * Renombra deadweight_tonnage → deadweight_tons y añade max_cargo_capacity
     */
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            // Rename column deadweight_tonnage to deadweight_tons
            if (Schema::hasColumn('vessels', 'deadweight_tonnage')) {
                $table->renameColumn('deadweight_tonnage', 'deadweight_tons');
            }

            // Add max_cargo_capacity column if it does not exist
            if (!Schema::hasColumn('vessels', 'max_cargo_capacity')) {
                $table->decimal('max_cargo_capacity', 10, 2)
                      ->default(0)
                      ->comment('Capacidad máxima de carga en toneladas')
                      ->after('deadweight_tons');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            // Rename column deadweight_tons back to deadweight_tonnage
            if (Schema::hasColumn('vessels', 'deadweight_tons')) {
                $table->renameColumn('deadweight_tons', 'deadweight_tonnage');
            }

            // Drop max_cargo_capacity column if exists
            if (Schema::hasColumn('vessels', 'max_cargo_capacity')) {
                $table->dropColumn('max_cargo_capacity');
            }
        });
    }
};