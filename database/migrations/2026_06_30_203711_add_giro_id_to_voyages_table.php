<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages', function (Blueprint $table) {
            // Lugar de giro de Aduana seleccionado para este viaje (campo 13 del archivo MANE/SIM).
            // Nullable: viajes previos no lo tienen y el campo es facultativo en la spec del SIM.
            $table->foreignId('giro_id')->nullable()->after('destination_customs_id')
                  ->constrained('giros')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('voyages', function (Blueprint $table) {
            $table->dropForeign(['giro_id']);
            $table->dropColumn('giro_id');
        });
    }
};