<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->string('currency_code', 3)
                ->nullable()
                ->default(null)
                ->change();
        });
    }

    public function down(): void
    {
        // El esquema anterior no admitía NULL.
        DB::table('shipment_items')
            ->whereNull('currency_code')
            ->update(['currency_code' => 'USD']);

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->string('currency_code', 3)
                ->nullable(false)
                ->default('USD')
                ->change();
        });
    }
};
