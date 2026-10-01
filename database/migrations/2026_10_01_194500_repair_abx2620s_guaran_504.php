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

        $vessel504Id = DB::table('vessels')
            ->where('company_id', $companyId)
            ->where('registration_number', '504')
            ->where('name', 'GUARAN F 504')
            ->value('id');

        if (!$companyId || !$ownerId || !$paraguayId || !$vessel504Id) {
            return;
        }

        DB::table('vessels')
            ->where('id', $vessel504Id)
            ->update([
                'owner_id' => $ownerId,
                'flag_country_id' => $paraguayId,
                'updated_at' => now(),
            ]);

        DB::table('voyages')
            ->where('id', 369)
            ->where('company_id', $companyId)
            ->where('voyage_number', 'ABX 2620S')
            ->update([
                'lead_vessel_id' => $vessel504Id,
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

        $vessel503Id = DB::table('vessels')
            ->where('company_id', $companyId)
            ->where('registration_number', '503')
            ->where('name', 'GUARAN F 503')
            ->value('id');

        $vessel504Id = DB::table('vessels')
            ->where('company_id', $companyId)
            ->where('registration_number', '504')
            ->where('name', 'GUARAN F 504')
            ->value('id');

        if ($vessel503Id && $vessel504Id) {
            DB::table('voyages')
                ->where('id', 369)
                ->where('company_id', $companyId)
                ->where('lead_vessel_id', $vessel504Id)
                ->update([
                    'lead_vessel_id' => $vessel503Id,
                    'updated_at' => now(),
                ]);
        }

        if ($vessel504Id) {
            DB::table('vessels')
                ->where('id', $vessel504Id)
                ->update([
                    'owner_id' => null,
                    'flag_country_id' => 2,
                    'updated_at' => now(),
                ]);
        }
    }
};
