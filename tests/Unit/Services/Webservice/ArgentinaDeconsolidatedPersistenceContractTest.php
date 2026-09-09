<?php

namespace Tests\Unit\Services\Webservice;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArgentinaDeconsolidatedPersistenceContractTest extends TestCase
{
    #[Test]
    public function structured_desc_responses_do_not_use_columns_missing_from_the_real_table(): void
    {
        $source = file_get_contents(
            app_path('Services/Simple/ArgentinaDeconsolidatedService.php')
        );

        $this->assertNotFalse($source);

        preg_match_all(
            '/WebserviceResponse::create\(\[(.*?)\]\);/s',
            $source,
            $matches
        );

        $this->assertCount(2, $matches[1], 'DESC debe persistir una respuesta estructurada tanto en éxito como en error.');

        $payloads = implode("\n", $matches[1]);

        foreach ([
            'external_reference',
            'bill_of_lading_numbers',
            'business_errors',
            'is_final_response',
            'additional_data',
        ] as $missingColumn) {
            $this->assertStringNotContainsString(
                "'{$missingColumn}' =>",
                $payloads,
                "webservice_responses no tiene la columna {$missingColumn}."
            );
        }
    }

    #[Test]
    public function desc_uses_existing_response_columns_for_warnings_errors_and_metadata(): void
    {
        $source = file_get_contents(
            app_path('Services/Simple/ArgentinaDeconsolidatedService.php')
        );

        $this->assertNotFalse($source);
        $this->assertStringContainsString("'validation_warnings' => \$result['details']", $source);
        $this->assertStringContainsString("'validation_errors' => \$result['details']", $source);
        $this->assertStringContainsString("'customs_metadata' => [", $source);
    }

    #[Test]
    public function accepted_afip_response_with_warnings_remains_success_not_partial_success(): void
    {
        $source = file_get_contents(
            app_path('Services/Simple/ArgentinaDeconsolidatedService.php')
        );

        $this->assertNotFalse($source);
        $this->assertStringContainsString("'response_type' => 'success'", $source);
        $this->assertStringNotContainsString(
            "'response_type' => \$result['details'] === [] ? 'success' : 'partial_success'",
            $source
        );
    }
}
