<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE client_contact_data
            MODIFY timezone VARCHAR(50) NULL DEFAULT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE client_contact_data
            MODIFY timezone VARCHAR(50) NULL
            DEFAULT 'America/Argentina/Buenos_Aires'
        ");
    }
};
