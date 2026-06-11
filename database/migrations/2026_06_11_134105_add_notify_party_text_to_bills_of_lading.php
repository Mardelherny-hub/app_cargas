<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            // Notificatario declarado como texto literal (ej. "SAME AS CONSIGNEE"),
            // cuando no corresponde a un cliente con datos propios. Respeta lo
            // declarado en el conocimiento sin crear un Client ficticio.
            $table->string('notify_party_text', 255)->nullable()->after('notify_party_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills_of_lading', function (Blueprint $table) {
            $table->dropColumn('notify_party_text');
        });
    }
};