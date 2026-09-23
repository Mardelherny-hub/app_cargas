<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\Concerns\ResolvesVoyageCargoType;
use DomainException;
use PHPUnit\Framework\TestCase;

class ImporterOperationTypeIntegrityContractTest extends TestCase
{
    private function resolver(): object
    {
        return new class {
            use ResolvesVoyageCargoType;

            public function resolve(
                string $home,
                string $origin,
                string $destination
            ): string {
                return $this->resolveVoyageCargoTypeCodes(
                    $home,
                    $origin,
                    $destination
                );
            }
        };
    }

    public function test_direction_is_relative_to_company_country(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('export', $resolver->resolve('AR', 'AR', 'PY'));
        $this->assertSame('import', $resolver->resolve('AR', 'PY', 'AR'));
        $this->assertSame('cabotage', $resolver->resolve('AR', 'AR', 'AR'));
        $this->assertSame('transit', $resolver->resolve('AR', 'PY', 'BR'));

        $this->assertSame('export', $resolver->resolve('PY', 'PY', 'AR'));
        $this->assertSame('import', $resolver->resolve('PY', 'AR', 'PY'));
    }

    public function test_missing_company_country_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->resolver()->resolve('', 'AR', 'PY');
    }

    public function test_all_non_cuscar_importers_use_shared_direction_contract(): void
    {
        foreach ([
            'KlineDataParser.php',
            'LoginXmlParser.php',
            'ParanaExcelParser.php',
            'GuaranExcelParser.php',
            'TfpTextParser.php',
            'NavsurTextParser.php',
            'G2OceanXmlParser.php',
        ] as $file) {
            $source = file_get_contents(
                dirname(__DIR__, 4)
                . '/app/Services/Parsers/' . $file
            );

            $this->assertStringContainsString(
                'ResolvesVoyageCargoType',
                $source,
                $file
            );

            $this->assertMatchesRegularExpression(
                '/resolveVoyageCargoType(?:ForCompany|Codes)/',
                $source,
                $file
            );
        }
    }
}
