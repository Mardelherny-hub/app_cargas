<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Simple verification seeder for existing catalogs
 *
 * Since catalogs already exist from FASE 0, this just verifies
 * they have the minimum data needed for client creation
 */
class QuickCatalogsVerifier extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔍 Verifying existing catalogs...');

        // Verify all required catalogs exist and have data
        $this->verifyCatalogs();

        $this->command->info('✅ Catalog verification completed!');
        $this->displaySummary();
    }

    /**
     * Verify all required catalogs
     */
    private function verifyCatalogs(): void
    {
        $catalogs = [
            'countries' => \App\Models\Country::class,
            'document_types' => \App\Models\DocumentType::class,
            'custom_offices' => \App\Models\CustomOffice::class,
            'ports' => \App\Models\Port::class,
            'cargo_types' => \App\Models\CargoType::class,
        ];

        foreach ($catalogs as $table => $model) {
            $count = $model::count();

            if ($count > 0) {
                $this->command->line("  ✓ {$table}: {$count} records");
            } else {
                $this->command->error("  ❌ {$table}: NO DATA - This will cause client creation to fail");
            }
        }
    }

    /**
     * Display summary of catalog data
     */
    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('=== 📊 CATALOG STATUS ===');

        // Show specific data needed for clients
        $argentina = \App\Models\Country::where('alpha2_code', 'AR')->first();
        $paraguay = \App\Models\Country::where('alpha2_code', 'PY')->first();

        if ($argentina) {
            $this->command->info("✅ Argentina found (ID: {$argentina->id})");
            $arDocTypes = \App\Models\DocumentType::where('country_id', $argentina->id)->count();
            $arPorts = \App\Models\Port::where('country_id', $argentina->id)->count();
            $arCustoms = \App\Models\CustomOffice::where('country_id', $argentina->id)->count();
            $this->command->info("   - Document types: {$arDocTypes}");
            $this->command->info("   - Ports: {$arPorts}");
            $this->command->info("   - Custom offices: {$arCustoms}");
        } else {
            $this->command->error("❌ Argentina not found");
        }

        if ($paraguay) {
            $this->command->info("✅ Paraguay found (ID: {$paraguay->id})");
            $pyDocTypes = \App\Models\DocumentType::where('country_id', $paraguay->id)->count();
            $pyPorts = \App\Models\Port::where('country_id', $paraguay->id)->count();
            $pyCustoms = \App\Models\CustomOffice::where('country_id', $paraguay->id)->count();
            $this->command->info("   - Document types: {$pyDocTypes}");
            $this->command->info("   - Ports: {$pyPorts}");
            $this->command->info("   - Custom offices: {$pyCustoms}");
        } else {
            $this->command->error("❌ Paraguay not found");
        }

        $companies = \App\Models\Company::where('active', true)->count();
        $this->command->info("✅ Active companies: {$companies}");

        $this->command->info('');

        if ($argentina && $paraguay && $companies > 0) {
            $this->command->info('🎯 READY FOR CLIENT CREATION!');
            $this->command->info('Run: php artisan db:seed --class=ClientsSeeder');
        } else {
            $this->command->error('❌ Missing required data. Please run catalog seeders first.');
        }
    }
}
