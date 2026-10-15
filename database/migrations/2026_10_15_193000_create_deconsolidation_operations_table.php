<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expediente propio de la desconsolidadora: no requiere barco ni
        // viaje local de la empresa transportista.
        // Los tres identificadores provienen de Datos a transmitir por ATA-DESC.
        Schema::create('deconsolidation_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('argentina_voyage_id', 16);
            $table->string('master_loading_port_code', 5);
            $table->string('master_bill_number', 18);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'argentina_voyage_id', 'master_loading_port_code', 'master_bill_number'],
                'uk_desc_operation_reference'
            );

            $table->foreign('company_id', 'fk_desc_operation_company')
                ->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('created_by_user_id', 'fk_desc_operation_user')
                ->references('id')->on('users')->onDelete('set null');
        });

        // Reutilizar BL, ítems y contenedores actuales para los títulos hijos,
        // sin asociarlos artificialmente a un Shipment/Voyage con embarcación.
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_id')->nullable()->change();
        });

        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->unsignedBigInteger('deconsolidation_operation_id')
                ->nullable()->after('shipment_id');

            $table->foreign('deconsolidation_operation_id')
                ->references('id')->on('deconsolidation_operations')->onDelete('restrict');

            $table->unique(
                ['deconsolidation_operation_id', 'bill_number'],
                'uk_bills_desc_operation_number'
            );
        });
    }

    public function down(): void
    {
        // No eliminar expedientes ni títulos independientes durante un
        // rollback: ambos necesitan tratamiento previo y explícito.
        if (
            DB::table('deconsolidation_operations')->exists()
            || DB::table('bills_of_lading')
                ->whereNull('shipment_id')
                ->orWhereNotNull('deconsolidation_operation_id')
                ->exists()
        ) {
            throw new \RuntimeException(
                'No se puede revertir: hay expedientes o conocimientos independientes de desconsolidación.'
            );
        }

        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropUnique('uk_bills_desc_operation_number');
            $table->dropForeign(['deconsolidation_operation_id']);
            $table->dropColumn('deconsolidation_operation_id');
        });

        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_id')->nullable(false)->change();
        });

        Schema::dropIfExists('deconsolidation_operations');
    }
};
