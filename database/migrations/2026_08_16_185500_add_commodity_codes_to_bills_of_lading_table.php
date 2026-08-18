<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->json('commodity_codes')
                ->nullable()
                ->after('commodity_code');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn('commodity_codes');
        });
    }
};
