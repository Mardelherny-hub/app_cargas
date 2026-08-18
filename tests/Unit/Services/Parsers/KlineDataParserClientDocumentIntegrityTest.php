<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Port;
use App\Services\Parsers\KlineDataParser;
use DomainException;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserClientDocumentIntegrityTest extends TestCase
{
    public function test_missing_explicit_tax_type_remains_unknown(): void
    {
        $country = Country::where(
            'alpha2_code',
            'AR'
        )->firstOrFail();

        $this->assertNull(
            $this->resolveDocumentType(
                null,
                $country->id
            )
        );
    }

    public function test_explicit_argentina_cuit_is_resolved(): void
    {
        $this->assertDocumentType(
            'AR',
            'CUIT'
        );
    }

    public function test_explicit_paraguay_ruc_is_resolved(): void
    {
        $this->assertDocumentType(
            'PY',
            'RUC'
        );
    }

    public function test_explicit_colombia_nit_is_resolved(): void
    {
        $this->assertDocumentType(
            'CO',
            'NIT'
        );
    }

    public function test_explicit_brazil_cnpj_is_resolved(): void
    {
        $this->assertDocumentType(
            'BR',
            'CNPJ'
        );
    }

    public function test_document_type_incompatible_with_country_is_rejected(): void
    {
        $country = Country::where(
            'alpha2_code',
            'CO'
        )->firstOrFail();

        $this->expectException(
            DomainException::class
        );

        $this->resolveDocumentType(
            'CUIT',
            $country->id
        );
    }

    public function test_imported_client_is_not_marked_verified(): void
    {
        $companyId = DB::table('companies')
            ->value('id');

        $portId = DB::table('ports')
            ->join(
                'countries',
                'countries.id',
                '=',
                'ports.country_id'
            )
            ->where(
                'countries.alpha2_code',
                'AR'
            )
            ->value('ports.id');

        $this->assertNotNull($companyId);
        $this->assertNotNull($portId);

        $port = Port::findOrFail($portId);

        DB::beginTransaction();

        try {
            $client = $this->invoke(
                'findOrCreateClient',
                [
                    [
                        'name' => 'KLINE QA CLIENT DOCUMENT 20260818',
                        'tax_id' => null,
                        'tax_type' => null,
                        'email' => null,
                        'address' => null,
                    ],
                    (int) $companyId,
                    [
                        'KLINE QA CLIENT DOCUMENT 20260818',
                        'ARGENTINA',
                    ],
                    $port,
                ]
            );

            $this->assertNull(
                $client->verified_at
            );

            $this->assertNull(
                $client->document_type_id
            );
        } finally {
            DB::rollBack();
        }
    }

    private function assertDocumentType(
        string $countryCode,
        string $documentCode
    ): void {
        $country = Country::where(
            'alpha2_code',
            $countryCode
        )->firstOrFail();

        $id = $this->resolveDocumentType(
            $documentCode,
            $country->id
        );

        $type = DocumentType::findOrFail($id);

        $this->assertSame(
            $documentCode,
            $type->code
        );

        $this->assertSame(
            $country->id,
            $type->country_id
        );
    }

    private function resolveDocumentType(
        ?string $taxType,
        ?int $countryId
    ): ?int {
        return $this->invoke(
            'resolveDocumentTypeId',
            [
                $taxType,
                $countryId,
            ]
        );
    }

    private function invoke(
        string $method,
        array $arguments
    ): mixed {
        $parser = new KlineDataParser();

        $reflection = new ReflectionMethod(
            $parser,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }
}
