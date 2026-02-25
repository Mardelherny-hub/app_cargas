<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->unsignedBigInteger('final_destination_customs_id')
                ->nullable()
                ->after('discharge_customs_id');

            $table->foreign('final_destination_customs_id')
                ->references('id')
                ->on('customs_offices')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropForeign(['final_destination_customs_id']);
            $table->dropColumn('final_destination_customs_id');
        });
    }
};