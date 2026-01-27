<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {   
        Schema::create('afip_customs_offices', function (Blueprint $table) {
            $table->id();
            
            $table->string('code', 3)->unique();
            $table->string('name', 100);
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['code', 'is_active'], 'afip_customs_code_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('afip_customs_offices');
    }
};
