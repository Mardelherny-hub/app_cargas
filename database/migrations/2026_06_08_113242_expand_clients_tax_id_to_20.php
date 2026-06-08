<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amplía clients.tax_id de varchar(11) a varchar(20).
     * Motivo: los CNPJ brasileños tienen 14 dígitos y no entraban en 11,
     * provocando "Data too long for column 'tax_id'" al importar Login XML
     * con consignees/notify de Brasil.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tax_id', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('tax_id', 11)->nullable(false)->change();
        });
    }
};