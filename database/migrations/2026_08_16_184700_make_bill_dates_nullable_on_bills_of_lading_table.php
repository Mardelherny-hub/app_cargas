<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dateTime('bill_date')
                ->nullable()
                ->change();

            $table->dateTime('loading_date')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dateTime('bill_date')
                ->nullable(false)
                ->change();

            $table->dateTime('loading_date')
                ->nullable(false)
                ->change();
        });
    }
};
