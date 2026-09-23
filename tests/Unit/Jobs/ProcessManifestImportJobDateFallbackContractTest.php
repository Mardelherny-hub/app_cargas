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

    public function test_operator_departure_replaces_parser_value_when_provided(): void
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
            '$voyage->departure_date = $this->departureDate;',
            $source
        );
        $this->assertStringNotContainsString(
            '&& !$voyage->departure_date',
            $source
        );
    }

    public function test_operator_loading_and_discharge_replace_source_values_when_provided(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            '$bill->loading_date = $this->loadingDate;',
            $source
        );
        $this->assertStringContainsString(
            '$bill->discharge_date = $this->dischargeDate;',
            $source
        );
        $this->assertStringContainsString(
            '$voyage->estimated_arrival_date = $this->dischargeDate;',
            $source
        );
    }

    public function test_compat_wrappers_follow_base_policy_without_fabricating_bill_date(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "preg_replace(\n            '/Compat$/'",
            $source
        );
        $this->assertStringNotContainsString(
            '$bill->bill_date = now()->toDateString();',
            $source
        );
    }

    public function test_operation_type_is_forwarded_to_manifest_parser(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'public ?string $operationType = null;',
            $source
        );
        $this->assertStringContainsString(
            "'operation_type' => \$this->operationType",
            $source
        );
    }

    public function test_job_no_longer_special_cases_cmsp_against_operator_discharge(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);
        $this->assertStringNotContainsString(
            '$operatorDischargeIsSource',
            $source
        );
        $this->assertStringNotContainsString(
            '$operatorLoadingIsSource',
            $source
        );
    }
}
