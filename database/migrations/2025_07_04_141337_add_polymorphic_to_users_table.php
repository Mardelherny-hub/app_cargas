<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Relación polimórfica
            $table->string('userable_type')->nullable()->after('email_verified_at');
            $table->unsignedBigInteger('userable_id')->nullable()->after('userable_type');

            // Campos adicionales simples
            $table->timestamp('last_access')->nullable()->after('updated_at');
            $table->boolean('active')->default(true)->after('last_access');
            $table->string('timezone', 50)->default('America/Argentina/Buenos_Aires')->after('active');

            // Índices
            $table->index(['userable_type', 'userable_id']);
            $table->index(['active', 'email_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['userable_type', 'userable_id']);
            $table->dropIndex(['active', 'email_verified_at']);

            $table->dropColumn([
                'userable_type',
                'userable_id',
                'last_access',
                'active',
                'timezone'
            ]);
        });
    }
};
