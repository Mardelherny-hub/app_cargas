<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo global de giros (lugar de giro de Aduana, campo 13 del archivo MANE/SIM).
        // Lista única para todas las empresas. Se va poblando con el uso: el operario
        // selecciona un giro existente o carga uno nuevo al armar el viaje (enfoque "find or create").
        Schema::create('giros', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 3)->unique();   // código de 3 dígitos que va al .mar (ej. "037")
            $table->string('descripcion', 100);       // nombre del giro (ej. "ZONA SECUNDARIA")
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giros');
    }
};