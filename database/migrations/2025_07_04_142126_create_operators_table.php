<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operators', function (Blueprint $table) {
            $table->id();
            $table->string('first_name'); // nombre
            $table->string('last_name'); // apellido
            $table->string('document_number', 20)->nullable(); // dni
            $table->string('phone')->nullable();
            $table->string('position')->nullable(); // cargo

            // Relationship with company (only for external operators)
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');

            // Operator type
            $table->enum('type', ['internal', 'external'])->default('external');

            // Specific permissions
            $table->json('special_permissions')->nullable(); // additional permissions
            $table->boolean('can_import')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_transfer')->default(false); // between companies

            // Status
            $table->boolean('active')->default(true);
            $table->timestamp('created_date')->useCurrent();
            $table->timestamp('last_access')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['company_id', 'type', 'active']);
            $table->index(['document_number', 'type']);
            $table->index(['active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operators');
    }
};
