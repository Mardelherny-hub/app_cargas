<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agregar campo 'state' (provincia/estado) a companies
     * Requerido por AFIP para elemento <domicilio><estado>
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('state', 100)
                ->nullable()
                ->after('city')
                ->comment('Provincia/Estado para webservices AFIP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};