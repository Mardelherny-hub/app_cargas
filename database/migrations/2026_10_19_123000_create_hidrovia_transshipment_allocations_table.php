<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reparto local de las líneas originales entre barcazas, sin duplicar BL. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hidrovia_transshipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_of_lading_id');
            $table->unsignedBigInteger('shipment_item_id');
            $table->unsignedBigInteger('target_shipment_id');
            $table->unsignedBigInteger('container_id')->nullable();
            $table->unsignedInteger('package_quantity');
            $table->decimal('gross_weight_kg', 12, 2);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('bill_of_lading_id', 'fk_ht_allocation_bill')
                ->references('id')->on('bills_of_lading')->restrictOnDelete();
            $table->foreign('shipment_item_id', 'fk_ht_allocation_item')
                ->references('id')->on('shipment_items')->restrictOnDelete();
            $table->foreign('target_shipment_id', 'fk_ht_allocation_target')
                ->references('id')->on('shipments')->restrictOnDelete();
            $table->foreign('container_id', 'fk_ht_allocation_container')
                ->references('id')->on('containers')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_ht_allocation_user')
                ->references('id')->on('users')->nullOnDelete();

            $table->index(['bill_of_lading_id', 'target_shipment_id'], 'idx_ht_bill_target');
            $table->unique(['shipment_item_id', 'target_shipment_id', 'container_id'], 'uk_ht_item_target_container');
            // Cuando container_id es NULL la BD permite múltiples filas; el
            // servicio bloquea el BL y valida también esa duplicidad.
        });
    }

    public function down(): void
    {
        // El rollback debe ser consciente: las asignaciones contienen
        // distribución declarada, no son datos temporales desechables.
        if (Schema::hasTable('hidrovia_transshipment_allocations')
            && \Illuminate\Support\Facades\DB::table('hidrovia_transshipment_allocations')->exists()) {
            throw new \RuntimeException('No se puede revertir: hay distribuciones de transbordo registradas.');
        }
        Schema::dropIfExists('hidrovia_transshipment_allocations');
    }
};
