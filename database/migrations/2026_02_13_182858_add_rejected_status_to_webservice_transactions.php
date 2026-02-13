<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE webservice_transactions MODIFY COLUMN status ENUM('pending','processing','sent','success','error','cancelled','retry','rejected','skipped') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE webservice_transactions MODIFY COLUMN status ENUM('pending','processing','sent','success','error','cancelled','retry') DEFAULT 'pending'");
    }
};