<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_trackings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vessel_id');

            $table->string('original_name');
            $table->string('stored_path');

            // queued / processing / completed / completed_with_warnings / failed
            $table->string('status')->default('queued');

            // Se llenan cuando el parser crea su registro / viaje (pueden quedar null si falla antes).
            $table->unsignedBigInteger('manifest_import_id')->nullable();
            $table->unsignedBigInteger('voyage_id')->nullable();

            // Mensaje claro para el usuario cuando falla (ej. "Este archivo ya fue importado").
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_trackings');
    }
};