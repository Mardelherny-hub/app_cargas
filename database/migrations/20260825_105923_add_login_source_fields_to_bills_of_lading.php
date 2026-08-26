<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            /*
             * Datos explícitos del conocimiento en archivos de naviera.
             *
             * No se reutilizan internal_reference/master_bill_number:
             * tienen otra semántica.
             */
            $table->string('booking_number', 100)
                ->nullable();

            $table->string('export_references', 255)
                ->nullable();

            $table->string('source_email', 255)
                ->nullable();

            $table->string('type_of_move', 20)
                ->nullable();

            /*
             * Ej.: 4X40HC.
             * Se conserva el dato fuente aunque también pueda reconstruirse
             * a partir de los contenedores físicos.
             */
            $table->string('container_summary', 50)
                ->nullable();

            /*
             * Login puede declarar más de un par UN / clase IMDG
             * para el mismo conocimiento.
             *
             * Los escalares un_number/imdg_class siguen disponibles
             * para compatibilidad, pero este campo es la fuente completa.
             */
            $table->json('dangerous_goods_details')
                ->nullable();

            /*
             * Proveniencia persistente necesaria para reglas de salida
             * específicas de la naviera, por ejemplo MANE Login.
             */
            $table->string('source_format', 32)
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn([
                'booking_number',
                'export_references',
                'source_email',
                'type_of_move',
                'container_summary',
                'dangerous_goods_details',
                'source_format',
            ]);
        });
    }
};
