<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')
            ->where('tax_id', '30612732503')
            ->value('id');

        $ownerId = DB::table('vessel_owners')
            ->where('company_id', $companyId)
            ->where('tax_id', '800738381')
            ->value('id');

        $paraguayId = DB::table('countries')
            ->where('alpha2_code', 'PY')
            ->where('codigo_afip', '221')
            ->value('id');

        if (!$companyId || !$ownerId || !$paraguayId) {
            return;
        }

        DB::table('vessels')
            ->where('company_id', $companyId)
            ->where('registration_number', '503')
            ->where('name', 'GUARAN F 503')
            ->update([
                'owner_id' => $ownerId,
                'flag_country_id' => $paraguayId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $companyId = DB::table('companies')
            ->where('tax_id', '30612732503')
            ->value('id');

        if (!$companyId) {
            return;
        }

        DB::table('vessels')
            ->where('company_id', $companyId)
            ->where('registration_number', '503')
            ->where('name', 'GUARAN F 503')
            ->where('owner_id', DB::table('vessel_owners')
                ->where('company_id', $companyId)
                ->where('tax_id', '800738381')
                ->value('id'))
            ->where('flag_country_id', DB::table('countries')
                ->where('alpha2_code', 'PY')
                ->value('id'))
            ->update([
                'owner_id' => null,
                'flag_country_id' => 2,
                'updated_at' => now(),
            ]);
    }
};
