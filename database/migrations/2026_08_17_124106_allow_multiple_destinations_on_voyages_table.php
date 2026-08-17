<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE voyages MODIFY destination_country_id BIGINT UNSIGNED NULL'
        );

        DB::statement(
            'ALTER TABLE voyages MODIFY destination_port_id BIGINT UNSIGNED NULL'
        );

        Schema::table('voyages', function (Blueprint $table) {
            $table->boolean('has_multiple_destinations')
                ->default(false)
                ->after('destination_port_id');
        });
    }

    public function down(): void
    {
        $unsafe = DB::table('voyages')
            ->whereNull('destination_country_id')
            ->orWhereNull('destination_port_id')
            ->orWhere('has_multiple_destinations', true)
            ->exists();

        if ($unsafe) {
            throw new \RuntimeException(
                'No se puede revertir: existen viajes sin destino único.'
            );
        }

        Schema::table('voyages', function (Blueprint $table) {
            $table->dropColumn('has_multiple_destinations');
        });

        DB::statement(
            'ALTER TABLE voyages MODIFY destination_country_id BIGINT UNSIGNED NOT NULL'
        );

        DB::statement(
            'ALTER TABLE voyages MODIFY destination_port_id BIGINT UNSIGNED NOT NULL'
        );
    }
};
