<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->integer('partial_packages')->nullable()->after('total_packages');
            $table->decimal('partial_weight_kg', 14, 3)->nullable()->after('gross_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn(['partial_packages', 'partial_weight_kg']);
        });
    }
};