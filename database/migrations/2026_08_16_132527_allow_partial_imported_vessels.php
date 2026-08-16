<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            /*
             * Una embarcación incorporada desde un archivo puede estar
             * documentalmente identificada aunque el archivo no informe
             * todavía todos sus datos registrales o técnicos.
             *
             * El alta manual seguirá exigiendo estos datos mediante
             * VesselController. Aquí sólo permitimos representar
             * correctamente la ausencia real de información.
             */

            $table->string('registration_number', 50)
                ->nullable()
                ->comment('Número de matrícula/registro')
                ->change();

            $table->unsignedBigInteger('vessel_type_id')
                ->nullable()
                ->comment('Tipo de embarcación')
                ->change();

            $table->unsignedBigInteger('flag_country_id')
                ->nullable()
                ->comment('País de bandera')
                ->change();

            $table->decimal('length_meters', 8, 2)
                ->nullable()
                ->comment('Longitud en metros')
                ->change();

            $table->decimal('beam_meters', 8, 2)
                ->nullable()
                ->comment('Manga en metros')
                ->change();

            $table->decimal('draft_meters', 8, 2)
                ->nullable()
                ->comment('Calado en metros')
                ->change();

            $table->decimal('cargo_capacity_tons', 10, 2)
                ->nullable()
                ->comment('Capacidad de carga en toneladas')
                ->change();

            $table->decimal('max_cargo_capacity', 10, 2)
                ->nullable()
                ->default(0)
                ->comment('Capacidad máxima de carga en toneladas')
                ->change();

            /*
             * Estos campos ya tenían defaults para el alta normal.
             * Se conservan esos defaults, pero se admite NULL para que
             * el importador pueda declarar explícitamente "desconocido".
             */
            $table->integer('engine_hours')
                ->nullable()
                ->default(0)
                ->comment('Horas de motor')
                ->change();

            $table->enum('ownership_type', [
                'owned',
                'chartered',
                'leased',
                'managed',
            ])
                ->nullable()
                ->default('owned')
                ->comment('Tipo de propiedad')
                ->change();

            $table->boolean('available_for_charter')
                ->nullable()
                ->default(true)
                ->comment('Disponible para fletamento')
                ->change();

            $table->integer('current_crew_size')
                ->nullable()
                ->default(0)
                ->comment('Tripulación actual')
                ->change();

            $table->boolean('crew_quarters_available')
                ->nullable()
                ->default(true)
                ->comment('Camarotes disponibles')
                ->change();

            $table->integer('passenger_capacity')
                ->nullable()
                ->default(0)
                ->comment('Capacidad de pasajeros')
                ->change();

            $table->integer('maintenance_interval_days')
                ->nullable()
                ->default(365)
                ->comment('Intervalo mantenimiento en días')
                ->change();

            $table->boolean('has_cranes')
                ->nullable()
                ->default(false)
                ->comment('Tiene grúas')
                ->change();

            $table->boolean('has_conveyor_system')
                ->nullable()
                ->default(false)
                ->comment('Tiene sistema transportador')
                ->change();

            $table->boolean('has_refrigeration')
                ->nullable()
                ->default(false)
                ->comment('Tiene refrigeración')
                ->change();

            $table->boolean('has_gps')
                ->nullable()
                ->default(true)
                ->comment('Tiene GPS')
                ->change();

            $table->boolean('has_radar')
                ->nullable()
                ->default(false)
                ->comment('Tiene radar')
                ->change();

            $table->boolean('has_ais')
                ->nullable()
                ->default(false)
                ->comment('Tiene AIS')
                ->change();

            $table->boolean('green_technology')
                ->nullable()
                ->default(false)
                ->comment('Tecnología verde')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->string('registration_number', 50)
                ->nullable(false)
                ->comment('Número de matrícula/registro')
                ->change();

            $table->unsignedBigInteger('vessel_type_id')
                ->nullable(false)
                ->comment('Tipo de embarcación')
                ->change();

            $table->unsignedBigInteger('flag_country_id')
                ->nullable(false)
                ->comment('País de bandera')
                ->change();

            $table->decimal('length_meters', 8, 2)
                ->nullable(false)
                ->comment('Longitud en metros')
                ->change();

            $table->decimal('beam_meters', 8, 2)
                ->nullable(false)
                ->comment('Manga en metros')
                ->change();

            $table->decimal('draft_meters', 8, 2)
                ->nullable(false)
                ->comment('Calado en metros')
                ->change();

            $table->decimal('cargo_capacity_tons', 10, 2)
                ->nullable(false)
                ->comment('Capacidad de carga en toneladas')
                ->change();

            $table->decimal('max_cargo_capacity', 10, 2)
                ->nullable(false)
                ->default(0)
                ->comment('Capacidad máxima de carga en toneladas')
                ->change();

            $table->integer('engine_hours')
                ->nullable(false)
                ->default(0)
                ->comment('Horas de motor')
                ->change();

            $table->enum('ownership_type', [
                'owned',
                'chartered',
                'leased',
                'managed',
            ])
                ->nullable(false)
                ->default('owned')
                ->comment('Tipo de propiedad')
                ->change();

            $table->boolean('available_for_charter')
                ->nullable(false)
                ->default(true)
                ->comment('Disponible para fletamento')
                ->change();

            $table->integer('current_crew_size')
                ->nullable(false)
                ->default(0)
                ->comment('Tripulación actual')
                ->change();

            $table->boolean('crew_quarters_available')
                ->nullable(false)
                ->default(true)
                ->comment('Camarotes disponibles')
                ->change();

            $table->integer('passenger_capacity')
                ->nullable(false)
                ->default(0)
                ->comment('Capacidad de pasajeros')
                ->change();

            $table->integer('maintenance_interval_days')
                ->nullable(false)
                ->default(365)
                ->comment('Intervalo mantenimiento en días')
                ->change();

            $table->boolean('has_cranes')
                ->nullable(false)
                ->default(false)
                ->comment('Tiene grúas')
                ->change();

            $table->boolean('has_conveyor_system')
                ->nullable(false)
                ->default(false)
                ->comment('Tiene sistema transportador')
                ->change();

            $table->boolean('has_refrigeration')
                ->nullable(false)
                ->default(false)
                ->comment('Tiene refrigeración')
                ->change();

            $table->boolean('has_gps')
                ->nullable(false)
                ->default(true)
                ->comment('Tiene GPS')
                ->change();

            $table->boolean('has_radar')
                ->nullable(false)
                ->default(false)
                ->comment('Tiene radar')
                ->change();

            $table->boolean('has_ais')
                ->nullable(false)
                ->default(false)
                ->comment('Tiene AIS')
                ->change();

            $table->boolean('green_technology')
                ->nullable(false)
                ->default(false)
                ->comment('Tecnología verde')
                ->change();
        });
    }
};
