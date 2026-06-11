<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // tax_id pasa a NULLABLE: cuando el documento importado no declara
        // identificador fiscal, el cliente queda con tax_id = NULL en lugar de
        // un número fabricado. Se preserva varchar(20) (estado real en prod).
        // El índice único (tax_id, country_id) se mantiene: en MySQL múltiples
        // NULL no violan la unicidad.
        DB::statement("ALTER TABLE clients MODIFY tax_id VARCHAR(20) NULL COMMENT 'CUIT/RUC/CNPJ real declarado. NULL si el origen no informa identificador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE clients MODIFY tax_id VARCHAR(20) NOT NULL COMMENT 'CUIT Argentina (11 dígitos) o RUC Paraguay'");
    }
};