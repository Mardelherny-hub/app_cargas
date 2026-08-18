<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use DomainException;
use ReflectionMethod;
use Tests\TestCase;

class KlineDataParserPartyIntegrityTest extends TestCase
{
    public function test_missing_party_name_remains_unknown(): void
    {
        $data = $this->invoke(
            'buildClientDataFromLines',
            [[]]
        );

        $this->assertNull($data['name']);
    }

    public function test_explicit_party_name_is_preserved(): void
    {
        $data = $this->invoke(
            'buildClientDataFromLines',
            [[
                'RENAULT ARGENTINA S.A.    CUIT: 30-50331781-4',
            ]]
        );

        $this->assertSame(
            'RENAULT ARGENTINA S.A.',
            $data['name']
        );
    }

    public function test_missing_client_name_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->invoke(
            'resolveRequiredClientName',
            [null]
        );
    }

    public function test_synthetic_unknown_name_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        $this->invoke(
            'resolveRequiredClientName',
            ['Cliente Desconocido']
        );
    }

    public function test_client_persistence_rejects_missing_name_before_lookup(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'K-Line no informa el nombre de una parte obligatoria del BL.'
        );

        $this->invoke(
            'findOrCreateClient',
            [[], 1, [], null]
        );
    }

    private function invoke(
        string $methodName,
        array $arguments
    ): mixed {
        $parser = new KlineDataParser();

        $method = new ReflectionMethod(
            $parser,
            $methodName
        );
        $method->setAccessible(true);

        return $method->invokeArgs(
            $parser,
            $arguments
        );
    }
}
