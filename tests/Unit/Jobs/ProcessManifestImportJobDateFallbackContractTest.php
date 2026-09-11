<?php

namespace Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;

class ProcessManifestImportJobDateFallbackContractTest extends TestCase
{
    public function test_common_import_date_policy_is_applied_before_tracking_completion(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);

        $applyPosition = strpos(
            $source,
            '$this->applyOperationalImportDates($parser, $result);'
        );

        $completePosition = strpos(
            $source,
            '$tracking->markCompleted('
        );

        $this->assertNotFalse($applyPosition);
        $this->assertNotFalse($completePosition);
        $this->assertLessThan($completePosition, $applyPosition);
    }

    public function test_source_departure_keeps_priority_over_manual_fallback(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '$this->departureDate !== null',
            $source
        );
        $this->assertStringContainsString(
            '&& !$voyage->departure_date',
            $source
        );
    }

    public function test_formats_without_specific_loading_date_use_operator_value(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);

        foreach ([
            'GuaranExcelParser',
            'LoginXmlParser',
            'ParanaExcelParser',
            'NavsurTextParser',
            'TfpTextParser',
            'KlineDataParser',
        ] as $parserName) {
            $this->assertStringContainsString(
                "'{$parserName}'",
                $source
            );
        }

        $this->assertStringContainsString(
            '$operatorLoadingIsSource',
            $source
        );
    }

    public function test_cmsp_preserves_source_discharge_and_other_formats_accept_operator_discharge(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '$operatorDischargeIsSource = $parserName !== \'CmspEdiParser\';',
            $source
        );
        $this->assertStringContainsString(
            '|| !$bill->discharge_date',
            $source
        );
    }
}
