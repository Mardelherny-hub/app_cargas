<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessel_owners', function (Blueprint $table) {
            $table->dropUnique('vessel_owners_tax_id_unique');
            $table->unique(
                ['company_id', 'tax_id'],
                'vessel_owners_company_tax_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vessel_owners', function (Blueprint $table) {
            $table->dropUnique('vessel_owners_company_tax_id_unique');
            $table->unique('tax_id', 'vessel_owners_tax_id_unique');
        });
    }
};
