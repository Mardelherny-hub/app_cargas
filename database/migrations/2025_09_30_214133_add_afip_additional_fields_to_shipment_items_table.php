<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->char('container_condition', 1)->nullable()->after('foreign_forwarder_country');
            $table->string('package_numbers', 100)->nullable()->after('container_condition');
            $table->char('packaging_type_code', 1)->nullable()->after('package_numbers');
            $table->char('discharge_customs_code', 3)->nullable()->after('packaging_type_code');
            $table->string('operational_discharge_code', 5)->nullable()->after('discharge_customs_code');
            $table->string('comments', 60)->nullable()->after('operational_discharge_code');
            $table->string('consignee_document_type', 4)->nullable()->after('comments');
            $table->string('consignee_tax_id', 11)->nullable()->after('consignee_document_type');
        });
    }

    public function down()
    {
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropColumn([
                'container_condition', 
                'package_numbers', 
                'packaging_type_code',
                'discharge_customs_code',
                'operational_discharge_code',
                'comments',
                'consignee_document_type',
                'consignee_tax_id'
            ]);       
        });
    }
};
