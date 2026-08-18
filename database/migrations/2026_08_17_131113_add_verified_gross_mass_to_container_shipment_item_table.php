<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->decimal('verified_gross_mass_kg', 12, 2)
                ->nullable()
                ->after('net_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->dropColumn('verified_gross_mass_kg');
        });
    }
};
