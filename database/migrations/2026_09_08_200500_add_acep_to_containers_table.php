<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->string('acep', 20)
                ->nullable()
                ->after('csc_expiry_date')
                ->comment('ACEP del contenedor para ATA-DESC; alternativo a csc_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn('acep');
        });
    }
};
