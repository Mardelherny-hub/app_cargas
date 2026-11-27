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

    public function test_alphanumeric_tax_id_is_removed_without_losing_address_components(): void
    {
        $resolver = $this->resolver();
        $client = new Client();
        foreach ([
            "CECIL STREET NO.07-01/02 SINGAPORE 069545\nTAX ID:200800970G\nTEL: +86-152-5556-5753"
                => 'CECIL STREET NO.07-01/02 SINGAPORE 069545',
            "158 CECIL STREET NR.07-01/02.\n069545 SINGAPORE\nTHE LEGAL/TAX ID:200800970G"
                => '158 CECIL STREET NR.07-01/02. 069545 SINGAPORE',
            "SHANGHAI, P.R.CHINA.\nTAX ID:91310109593111506H"
                => 'SHANGHAI, P.R.CHINA.',
            "TAX ID: 91320903MA22XL4D1N\nADD: 2 JINGSAN ROAD, YANDU STREET"
                => '2 JINGSAN ROAD, YANDU STREET',
            'AVENIDA 200800970G, SINGAPORE' => 'AVENIDA 200800970G, SINGAPORE',
        ] as $source => $expected) {
            $this->assertSame($expected, $resolver->cleanForClient($client, $source));
        }
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

    public function test_taxid1_and_tax_dot_id_labels_are_removed_with_their_number(): void
    {
        $this->assertSame(
            'TENIENTE VERA 3148/CASI CORNEL ESCURRA BARRIO: YKUA SATI ASUNCION . PARAGUAY',
            $this->resolver()->cleanForClient(
                new Client(),
                'TENIENTE VERA 3148/CASI CORNEL ESCURRA BARRIO: YKUA SATI ASUNCION . PARAGUAY TAXID1 : 80044515-5'
            )
        );
        $this->assertSame(
            'AV. SAN MARTIN 504 ASUNCION',
            $this->resolver()->cleanForClient(
                new Client(),
                'AV. SAN MARTIN 504 ASUNCION TAX.ID:80027640-0'
            )
        );
    }

    public function test_address_after_contact_line_is_kept(): void
    {
        $resolver = $this->resolver();

        $this->assertSame(
            'CALLE FICTICIA 1331 CIUDAD DEMO, PARAGUAY',
            $resolver->cleanForClient(new Client(), "PH: 595 900 000 000\nCALLE FICTICIA 1331\nCIUDAD DEMO, PARAGUAY")
        );
        $this->assertSame(
            '15 DE EJEMPLO 432 ENTRE CALLE A Y CALLE B',
            $resolver->cleanForClient(new Client(), "MAIL: DEMO@EJEMPLO.COM.PY\n15 DE EJEMPLO 432 ENTRE CALLE A Y CALLE B")
        );
        $this->assertSame(
            'MAYOR FICTICIO 583 CIUDAD DEMO-PARAGUAY',
            $resolver->cleanForClient(new Client(), "PHONE 900.000\nMAYOR FICTICIO 583\nRUC 99999993-0\nCIUDAD DEMO-PARAGUAY")
        );
    }

    public function test_street_line_without_number_after_contact_is_kept(): void
    {
        $this->assertSame(
            'AVDA. FICTICIA E/ EJEMPLO EDIFICIO DEMO, 5TO PISO',
            $this->resolver()->cleanForClient(new Client(), "PHONE: +595 - 000000000\nAVDA. FICTICIA E/ EJEMPLO\nEDIFICIO DEMO, 5TO PISO")
        );
    }

    public function test_party_with_only_contact_lines_gets_no_address(): void
    {
        $resolver = $this->resolver();

        $this->assertNull($resolver->cleanForClient(new Client(), "ATN: SR. PERSONA DEMO / SR. OTRA\nAPELLIDO\nTEL: (59521) 000 0000\nRUC: 99999993-0"));
        $this->assertNull($resolver->cleanForClient(new Client(), "CONTACTO: PERSONA DEMO\nTELEFONO: +595900000000\nE-MAIL:\nPERSONA@EJEMPLO.COM.P"));
        $this->assertNull($resolver->cleanForClient(new Client(), "ATTN: PERSONA DEMO\nT/F : 59500000000 / 59500000001\nE-MAIL: PERSONA@EJEMPLO.COM;"));
    }

    public function test_attention_line_ends_where_the_street_starts(): void
    {
        $this->assertSame(
            'AV. FICTICIA 7180. CIUDAD DEMO, PARAGUAY',
            $this->resolver()->cleanForClient(
                new Client(),
                'RUC 99999993-0 ATN. SR. PERSONA DEMO AV. FICTICIA 7180. CIUDAD DEMO, PARAGUAY TEL. 595 21 000000'
            )
        );
    }

    public function test_trailing_contact_on_the_same_line_is_still_removed(): void
    {
        $this->assertSame(
            'AV. EJEMPLO 123 ASUNCION',
            $this->resolver()->cleanForClient(
                new Client(),
                'AV. EJEMPLO 123 ASUNCION TEL: 021 000 000 FAX 021 000 001'
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
