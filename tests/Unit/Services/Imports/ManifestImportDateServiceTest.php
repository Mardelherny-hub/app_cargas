<?php

namespace Tests\Unit\Services\Imports;

use App\Services\Imports\ManifestImportDateService;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\G2OceanXmlParser;
use App\Services\Parsers\GuaranExcelParser;
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use Tests\TestCase;

class ManifestImportDateServiceTest extends TestCase
{
    private ManifestImportDateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ManifestImportDateService::class);
    }

    public function test_manual_loading_replaces_non_source_defaults_for_formats_without_explicit_bl_loading_date(): void
    {
        $parsers = [
            GuaranExcelParser::class,
            KlineDataParser::class,
            LoginXmlParser::class,
            NavsurTextParser::class,
            ParanaExcelParser::class,
            TfpTextParser::class,
        ];

        foreach ($parsers as $parserClass) {
            $this->assertTrue(
                $this->service->shouldApplyManualLoading(
                    $parserClass,
                    true
                ),
                "{$parserClass} debe aceptar la fecha manual de carga porque su loading_date actual no representa una fecha explícita propia del BL."
            );
        }
    }

    public function test_non_whitelisted_formats_preserve_an_existing_loading_date(): void
    {
        foreach ([G2OceanXmlParser::class, CmspEdiParser::class] as $parserClass) {
            $this->assertFalse(
                $this->service->shouldApplyManualLoading(
                    $parserClass,
                    true
                ),
                "{$parserClass} debe conservar una loading_date no nula ya resuelta por su parser."
            );
        }
    }

    public function test_missing_loading_date_always_accepts_manual_fallback(): void
    {
        foreach ([
            GuaranExcelParser::class,
            KlineDataParser::class,
            LoginXmlParser::class,
            NavsurTextParser::class,
            ParanaExcelParser::class,
            TfpTextParser::class,
            G2OceanXmlParser::class,
            CmspEdiParser::class,
        ] as $parserClass) {
            $this->assertTrue(
                $this->service->shouldApplyManualLoading(
                    $parserClass,
                    false
                )
            );
        }
    }

    public function test_common_service_never_updates_documentary_bill_date(): void
    {
        $source = file_get_contents(
            app_path('Services/Imports/ManifestImportDateService.php')
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            "\$changes['bill_date']",
            $source
        );
    }

    public function test_job_applies_operational_dates_inside_atomic_transaction(): void
    {
        $source = file_get_contents(
            app_path('Jobs/ProcessManifestImportJob.php')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'ManifestImportDateService::class',
            $source
        );
        $this->assertStringContainsString(
            'DB::transaction($parse)',
            $source
        );
    }
}
