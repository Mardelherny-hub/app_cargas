<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->string('id_mic_dta_pri_fracc', 20)->nullable()->after('partial_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn('id_mic_dta_pri_fracc');
        });
    }
};