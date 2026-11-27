<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El peso bruto que se declara (peso, pesoBruto, pesoBrutoTotFrac) es decimal
 * sin límite de decimales en el WSDL, y los archivos traen hasta 3. Las columnas
 * guardaban 2 y redondeaban. Se pasa a 3 decimales, con la misma parte entera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 13, 3)->comment('Peso bruto en kilogramos')->change();
        });
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 13, 3)->comment('Peso bruto en kilogramos')->change();
        });
        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 13, 3)->nullable()->comment('Peso bruto en este contenedor')->change();
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en kilogramos')->change();
        });
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 12, 2)->comment('Peso bruto en kilogramos')->change();
        });
        Schema::table('container_shipment_item', function (Blueprint $table) {
            $table->decimal('gross_weight_kg', 12, 2)->nullable()->comment('Peso bruto en este contenedor')->change();
        });
    }
};
