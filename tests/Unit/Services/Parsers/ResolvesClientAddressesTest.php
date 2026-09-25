<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Client;
use App\Models\ClientContactData;
use App\Services\Parsers\Concerns\ResolvesClientAddresses;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use Tests\TestCase;

class ResolvesClientAddressesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_imported_different_address_is_preserved_but_not_activated(): void
    {
        $primary = new ClientContactData();
        $primary->id = 321;
        $primary->address_line_1 = 'CHILE 801, BUENOS AIRES';

        $relation = Mockery::mock(HasMany::class);
        $relation
            ->shouldReceive('where')
            ->once()
            ->with('is_primary', true)
            ->andReturnSelf();
        $relation
            ->shouldReceive('first')
            ->once()
            ->andReturn($primary);

        $client = Mockery::mock(Client::class)->makePartial();
        $client
            ->shouldReceive('contactData')
            ->once()
            ->andReturn($relation);

        $result = $this->resolver()->resolve(
            $client,
            'AV. DEL LIBERTADOR 1500, BUENOS AIRES',
            'consignee'
        );

        $this->assertNotNull($result);
        $this->assertSame(321, $result['client_contact_data_id']);
        $this->assertSame('consignee', $result['role']);
        $this->assertFalse($result['use_specific_data']);
        $this->assertSame(
            'AV. DEL LIBERTADOR 1500, BUENOS AIRES',
            $result['specific_address_line_1']
        );
    }

    public function test_known_tax_id_is_removed_from_start_of_imported_address(): void
    {
        $client = new Client();
        $client->tax_id = '800521340';

        $this->assertSame(
            'LUQYE, PARAGUAY T',
            $this->resolver()->cleanForClient(
                $client,
                '80052134-0 LUQYE, PARAGUAY T'
            )
        );
    }

    public function test_tax_prefixed_fragment_is_not_promoted_to_master_address(): void
    {
        $client = new Client();
        $client->tax_id = '800521340';

        $this->assertFalse(
            $this->resolver()->persist(
                $client,
                '80052134-0 LUQYE, PARAGUAY T'
            )
        );
    }

    public function test_unrelated_leading_number_is_preserved_as_address(): void
    {
        $client = new Client();
        $client->tax_id = '800521340';

        $this->assertSame(
            '674 MADAME LINCH, LUQUE',
            $this->resolver()->cleanForClient(
                $client,
                '674 MADAME LINCH, LUQUE'
            )
        );
    }

    public function test_same_address_does_not_create_specific_contact(): void
    {
        $primary = new ClientContactData();
        $primary->id = 654;
        $primary->address_line_1 = 'Chile 801, Buenos Aires';

        $relation = Mockery::mock(HasMany::class);
        $relation
            ->shouldReceive('where')
            ->once()
            ->with('is_primary', true)
            ->andReturnSelf();
        $relation
            ->shouldReceive('first')
            ->once()
            ->andReturn($primary);

        $client = Mockery::mock(Client::class)->makePartial();
        $client
            ->shouldReceive('contactData')
            ->once()
            ->andReturn($relation);

        $result = $this->resolver()->resolve(
            $client,
            '  CHILE   801, BUENOS AIRES  ',
            'shipper'
        );

        $this->assertNull($result);
    }

    private function resolver(): object
    {
        return new class {
            use ResolvesClientAddresses;

            public function resolve(
                Client $client,
                ?string $fileAddress,
                string $role
            ): ?array {
                return $this->resolveSpecificAddress(
                    $client,
                    $fileAddress,
                    $role
                );
            }

            public function cleanForClient(
                Client $client,
                ?string $fileAddress
            ): ?string {
                $cleaned = $this->cleanFileAddress($fileAddress);

                return $this->removeLeadingKnownTaxId(
                    $client,
                    $cleaned
                );
            }

            public function persist(
                Client $client,
                ?string $fileAddress
            ): bool {
                return $this->persistClientAddress(
                    $client,
                    $fileAddress
                );
            }
        };
    }
}
