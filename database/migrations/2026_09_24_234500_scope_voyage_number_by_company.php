<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voyages', function (Blueprint $table) {
            $table->dropUnique('voyages_voyage_number_unique');
            $table->unique(
                ['company_id', 'voyage_number'],
                'voyages_voyage_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('voyages', function (Blueprint $table) {
            $table->dropUnique('voyages_voyage_number_unique');
            $table->unique(
                'voyage_number',
                'voyages_voyage_number_unique'
            );
        });
    }
};
