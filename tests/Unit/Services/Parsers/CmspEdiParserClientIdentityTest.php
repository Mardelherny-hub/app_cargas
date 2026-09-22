<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\CmspEdiParserCompat;
use ReflectionMethod;
use Tests\TestCase;

class CmspEdiParserClientIdentityTest extends TestCase
{
    protected function invoke(
        CmspEdiParser $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            CmspEdiParser::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    protected function compatWithoutDatabase(): CmspEdiParserCompat
    {
        return new class extends CmspEdiParserCompat {
            protected function countryIdForAlpha2(string $alpha2): int
            {
                return match (strtoupper($alpha2)) {
                    'PY' => 101,
                    'CO' => 202,
                    default => 999,
                };
            }
        };
    }

    protected function invokeCompat(
        CmspEdiParserCompat $parser,
        string $method,
        array $arguments = []
    ): mixed {
        $reflection = new ReflectionMethod(
            CmspEdiParserCompat::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $parser,
            $arguments
        );
    }

    public function test_real_adz_preserves_tax_without_inventing_cuit(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'MSG S.R.L. BUENOS AIRES - ARGENTINA',
                'address' => null,
                'type' => 'consignee',
                'tax_id' => '30-712412093',
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            '30712412093',
            $identity['tax_id']
        );

        // RFF+ADZ identifica fiscalmente, pero no declara que sea CUIT.
        $this->assertNull(
            $identity['tax_type']
        );
    }

    public function test_explicit_cuit_is_preserved_as_cuit(): void
    {
        $parser = new CmspEdiParser();

        $taxId = '30585343427';

        $taxType = $this->invoke(
            $parser,
            'extractExplicitTaxTypeFromText',
            [
                'AGENCIA MARITIMA INTERNACIONAL SA CUIT 30-58534342-7 DIRECCION',
                $taxId,
            ]
        );

        $this->assertSame('CUIT', $taxType);
    }

    public function test_real_parties_provide_country_from_nad_text(): void
    {
        $parser = new CmspEdiParser();

        $this->assertSame(
            'AR',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['MSG S.R.L. BUENOS AIRES - ARGENTINA']
            )
        );

        $this->assertSame(
            'PY',
            $this->invoke(
                $parser,
                'countryAlpha2FromPartyText',
                ['CMSP S.A. ASUNCION - PARAGUAY']
            )
        );
    }

    public function test_party_without_tax_gets_no_fake_identity(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'CMSP S.A. ASUNCION - PARAGUAY',
                'address' => null,
                'type' => 'shipper',
                'tax_id' => null,
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            [
                'tax_id' => null,
                'tax_type' => null,
            ],
            $identity
        );
    }

    public function test_generic_tax_id_does_not_invent_document_type(): void
    {
        $parser = new CmspEdiParser();

        $identity = $this->invoke(
            $parser,
            'resolvePartyTaxIdentity',
            [[
                'name' => 'EMPRESA TAX ID: 92102433000923',
                'address' => 'PARAGUAY',
                'type' => 'shipper',
                'tax_id' => null,
                'tax_type' => null,
            ]]
        );

        $this->assertSame(
            '92102433000923',
            $identity['tax_id']
        );

        $this->assertNull(
            $identity['tax_type']
        );
    }

    public function test_explicit_types_define_their_jurisdiction(): void
    {
        $parser = new CmspEdiParser();

        $cases = [
            'CUIT' => 'AR',
            'RUC' => 'PY',
            'CNPJ' => 'BR',
            'NIT' => 'CO',
        ];

        foreach ($cases as $type => $country) {
            $this->assertSame(
                $country,
                $this->invoke(
                    $parser,
                    'countryAlpha2ForTaxType',
                    [$type]
                )
            );
        }
    }


    public function test_compat_prefers_explicit_paraguay_when_nit_label_is_ambiguous(): void
    {
        $parser = $this->compatWithoutDatabase();

        $countryId = $this->invokeCompat(
            $parser,
            'resolveClientCountryId',
            [[
                'name' => 'DARNEL PARAGUAY S.A.',
                'address' => 'NIT 801005175 MARIANO ROQUE ALONSO PARAGUAY',
                'type' => 'consignee',
                'tax_id' => '801005175',
                'tax_type' => 'NIT',
            ], 'NIT']
        );

        $this->assertSame(101, $countryId);
    }

    public function test_compat_keeps_colombia_for_nit_when_source_declares_colombia(): void
    {
        $parser = $this->compatWithoutDatabase();

        $countryId = $this->invokeCompat(
            $parser,
            'resolveClientCountryId',
            [[
                'name' => 'AJOVER DARNEL S.A.S.',
                'address' => 'NIT 860.013.771-7 BOGOTA - COLOMBIA',
                'type' => 'shipper',
                'tax_id' => '8600137717',
                'tax_type' => 'NIT',
            ], 'NIT']
        );

        $this->assertSame(202, $countryId);
    }

    public function test_compat_does_not_invent_document_type_for_ambiguous_nit(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/CmspEdiParserCompat.php')
        );

        $this->assertStringContainsString(
            "\$taxType !== 'NIT'",
            $source
        );

        $this->assertStringContainsString(
            'se conserva el identificador sin inventar tipo',
            $source
        );
    }


    public function test_invalid_tax_warning_is_contextualized_during_nad_parse(): void
    {
        $parser = new class extends CmspEdiParserCompat {
            public function parsePartyForTest(
                array $segment,
                ?array &$currentContainer = null
            ): void {
                $this->parseParty(
                    $segment,
                    $currentContainer
                );
            }

            public function warningsForTest(): array
            {
                return $this->stats['warnings'];
            }
        };

        $container = [
            'references' => [
                'bill_number' => '001PJSM35026',
            ],
            'parties' => [],
        ];

        $parser->parsePartyForTest(
            [
                'elements' => [
                    'CN',
                    '',
                    'AGENCIA X CUIT 33-70504237-10 PARAGUAY',
                ],
            ],
            $container
        );

        $warnings = $parser->warningsForTest();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString(
            'BL 001PJSM35026',
            $warnings[0]
        );
        $this->assertStringContainsString(
            'parte consignee',
            $warnings[0]
        );
        $this->assertStringContainsString(
            'AGENCIA X',
            $warnings[0]
        );
    }

    public function test_fiscal_warning_includes_bill_party_and_name_context(): void
    {
        $parser = new CmspEdiParserCompat();

        $this->invokeCompat(
            $parser,
            'extractExplicitTaxTypeFromText',
            ['AGENCIA X CUIT 33-70504237-10']
        );

        $this->invokeCompat(
            $parser,
            'contextualizePartyWarnings',
            [0, [
                '_context_bl_number' => '001PJSM35026',
                '_context_role' => 'consignee',
                'name' => 'AGENCIA X',
            ]]
        );

        $stats = new \ReflectionProperty(
            CmspEdiParser::class,
            'stats'
        );
        $stats->setAccessible(true);
        $warnings = $stats->getValue($parser)['warnings'];

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString(
            'BL 001PJSM35026',
            $warnings[0]
        );
        $this->assertStringContainsString(
            'parte consignee',
            $warnings[0]
        );
        $this->assertStringContainsString(
            'AGENCIA X',
            $warnings[0]
        );
    }


    public function test_cuscar_operation_type_is_explicit_and_not_inferred_from_ports(): void
    {
        $parser = new CmspEdiParserCompat();

        $this->assertSame(
            'import',
            $this->invokeCompat(
                $parser,
                'resolveCuscarOperationType',
                [['operation_type' => 'IMPORT']]
            )
        );

        $this->assertSame(
            'export',
            $this->invokeCompat(
                $parser,
                'resolveCuscarOperationType',
                [['operation_type' => 'export']]
            )
        );

        $this->expectException(\DomainException::class);

        $this->invokeCompat(
            $parser,
            'resolveCuscarOperationType',
            [[]]
        );
    }

    public function test_real_josamo_45u1_maps_to_40ot(): void
    {
        $parser = new CmspEdiParser();

        $this->assertSame(
            '40OT',
            $this->invoke(
                $parser,
                'mapIsoContainerType',
                ['45U1']
            )
        );
    }

    public function test_container_type_resolution_prefers_exact_source_iso(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/CmspEdiParser.php')
        );

        $resolverStart = strpos(
            $source,
            'protected function resolveActiveContainerTypeByIso'
        );

        $resolverEnd = strpos(
            $source,
            'protected function findOrCreatePort',
            $resolverStart
        );

        $this->assertNotFalse($resolverStart);
        $this->assertNotFalse($resolverEnd);

        $resolver = substr(
            $source,
            $resolverStart,
            $resolverEnd - $resolverStart
        );

        $isoLookup = strpos($resolver, "UPPER(TRIM(iso_code))");
        $mappingFallback = strpos(
            $resolver,
            '$this->mapIsoContainerType($normalized)'
        );

        $this->assertNotFalse($isoLookup);
        $this->assertNotFalse($mappingFallback);
        $this->assertLessThan($mappingFallback, $isoLookup);
    }

    public function test_hapag_real_iso_variants_map_to_supported_catalog_types(): void
    {
        $parser = new CmspEdiParser();

        $this->assertSame(
            '40RH',
            $this->invoke(
                $parser,
                'mapIsoContainerType',
                ['45R5']
            )
        );

        $this->assertSame(
            '45HC',
            $this->invoke(
                $parser,
                'mapIsoContainerType',
                ['L5G1']
            )
        );
    }

    public function test_cni_level_ftx_before_first_gid_is_not_lost(): void
    {
        $parser = new CmspEdiParser();

        $segments = [
            [
                'tag' => 'CNI',
                'elements' => ['1'],
            ],
            [
                'tag' => 'RFF',
                'elements' => ['BM:BUEFNX26P104010'],
            ],
            [
                'tag' => 'FTX',
                'elements' => [
                    'AAA',
                    '',
                    '',
                    'TOTAL ITEMS: 17 PALLET BOX PRODUCTOS FARMACEUTICOS',
                ],
            ],
            [
                'tag' => 'GID',
                'elements' => ['1', '17:PX:::PALLET BOX'],
            ],
        ];

        $edi = new \ReflectionProperty(
            CmspEdiParser::class,
            'ediSegments'
        );
        $edi->setAccessible(true);
        $edi->setValue($parser, $segments);

        $this->invoke(
            $parser,
            'extractStructuredData'
        );

        $parsed = new \ReflectionProperty(
            CmspEdiParser::class,
            'parsedData'
        );
        $parsed->setAccessible(true);
        $data = $parsed->getValue($parser);

        $this->assertSame(
            'TOTAL ITEMS: 17 PALLET BOX PRODUCTOS FARMACEUTICOS',
            $data['containers'][0]['items'][0]['description']
        );
    }

    public function test_multiline_ftx_segment_is_not_dropped_before_description_parse(): void
    {
        $parser = new CmspEdiParser();

        $tmp = tempnam(sys_get_temp_dir(), 'cuscar-ftx-');

        file_put_contents(
            $tmp,
            "UNH+1+CUSCAR:D:96B:UN'\n"
            . "CNI+1++'\n"
            . "RFF+BM:BUEFNX26P104010'\n"
            . "GID+1+820:BG:::PLT'\n"
            . "FTX+AAA+++TOTAL ITEMS: 17 PALLET BOX\r\n"
            . "15 PALLET BOX\r\n"
            . "PRODUCTOS FARMACEUTICOS +20C\r\n"
            . "HS-CODE : 30 04 90\r\n"
            . "100,000 KGM '\n"
        );

        try {
            $this->invoke($parser, 'parseEdiFile', [$tmp]);

            $segmentsProperty = new \ReflectionProperty(
                CmspEdiParser::class,
                'ediSegments'
            );
            $segmentsProperty->setAccessible(true);
            $segments = $segmentsProperty->getValue($parser);

            $ftx = collect($segments)
                ->firstWhere('tag', 'FTX');

            $this->assertNotNull($ftx);

            $literal = $this->invoke(
                $parser,
                'extractFtxDescription',
                [$ftx]
            );

            $this->assertStringContainsString(
                'PRODUCTOS FARMACEUTICOS +20C',
                $literal
            );

            $this->invoke($parser, 'extractStructuredData');

            $parsedProperty = new \ReflectionProperty(
                CmspEdiParser::class,
                'parsedData'
            );
            $parsedProperty->setAccessible(true);
            $data = $parsedProperty->getValue($parser);

            $this->assertStringContainsString(
                'PRODUCTOS FARMACEUTICOS +20C',
                $data['containers'][0]['items'][0]['description']
            );
        } finally {
            @unlink($tmp);
        }
    }

    public function test_hapag_group_without_aax_uses_distinct_item_weights(): void
    {
        $parser = new CmspEdiParser();

        $weight = $this->invoke(
            $parser,
            'resolveGroupGrossWeight',
            [[
                'items' => [
                    [
                        'sequence' => '1',
                        'package_info' => '15:PX:::PALLET BOX',
                        'description' => 'PRODUCTOS FARMACEUTICOS +20C',
                        'gross_weight_kg' => 1706,
                        'containers' => [],
                    ],
                    [
                        'sequence' => '1',
                        'package_info' => '15:PX:::PALLET BOX',
                        'description' => 'PRODUCTOS FARMACEUTICOS +20C',
                        'gross_weight_kg' => 1706,
                        'containers' => ['CONT0000001'],
                    ],
                    [
                        'sequence' => '2',
                        'package_info' => '2:PX:::PALLET BOX',
                        'description' => 'PRODUCTOS FARMACEUTICOS +20C',
                        'gross_weight_kg' => 100,
                        'containers' => [],
                    ],
                ],
            ], 'HLCUBC1250954817']
        );

        $this->assertSame(1806.0, $weight);
    }

    public function test_real_hapag_party_country_names_are_resolved_from_source_text(): void
    {
        $parser = new CmspEdiParser();

        $cases = [
            ['TH', 'CHONBURI 20230 THAILAND'],
            ['IN', 'TELANGANA, INDIA-500084'],
            ['US', 'MIAMI, FL 33172. UNITEDSTATES'],
            ['US', 'HOUSTON, TEXAS 77060 USA'],
            ['ES', 'FUENLABRADA - MADRID'],
            ['AE', 'DUBAI, UNITED ARAB EMIRATES'],
            ['HK', 'WAN CHAI DISTRICT, HONG KONG'],
            ['MX', 'TLAQUEPAQUE, JAL, MEXICO 45500'],
            ['CN', 'ZHEJIANG PROVINCE, CHINA'],
            ['JP', 'SHIZUOKA 419-0201 JAPAN'],
            ['SG', 'CECIL STREET SINGAPORE 069545'],
            ['PA', 'COLON FREE ZONE, PANAMA'],
            ['KR', 'SEOUL, KOREA'],
            ['AU', 'KILSYTH VIC 3137 AUSTRALIA'],
            ['PY', 'NEMBY CENTRAL 22210 PRY'],
        ];

        foreach ($cases as [$expected, $text]) {
            $this->assertSame(
                $expected,
                $this->invoke(
                    $parser,
                    'countryAlpha2FromPartyText',
                    [$text]
                ),
                $text
            );
        }
    }

    public function test_real_josamo_eqd_8169_marks_blank_item_as_empty(): void
    {
        $parser = new CmspEdiParser();

        $segments = [
            [
                'tag' => 'EQD',
                'elements' => [
                    'CN',
                    'BEAU6267394',
                    '45G1::5',
                    '2',
                    '3',
                    '4',
                ],
            ],
            [
                'tag' => 'CNI',
                'elements' => ['39', 'JOSPSFV350S', '001PJSM35026'],
            ],
            [
                'tag' => 'RFF',
                'elements' => ['BM:001PJSM35026'],
            ],
            [
                'tag' => 'GID',
                'elements' => ['0', '0::::'],
            ],
            [
                'tag' => 'FTX',
                'elements' => ['AAA', '', '', ''],
            ],
            [
                'tag' => 'MEA',
                'elements' => ['AAY', 'G', 'KGM:0'],
            ],
            [
                'tag' => 'SGP',
                'elements' => ['BEAU6267394', '0'],
            ],
        ];

        $edi = new \ReflectionProperty(
            CmspEdiParser::class,
            'ediSegments'
        );
        $edi->setAccessible(true);
        $edi->setValue($parser, $segments);

        $this->invoke(
            $parser,
            'extractStructuredData'
        );

        $parsed = new \ReflectionProperty(
            CmspEdiParser::class,
            'parsedData'
        );
        $parsed->setAccessible(true);
        $data = $parsed->getValue($parser);

        $this->assertSame(
            '4',
            $data['equipment']['BEAU6267394']['full_empty_indicator']
        );

        $item = $data['containers'][0]['items'][0];

        $this->assertSame('', $item['description']);
        $this->assertTrue(
            $this->invoke(
                $parser,
                'isEmptyContainerItem',
                [$item]
            )
        );
    }

    public function test_eqd_full_indicator_does_not_infer_empty(): void
    {
        $parser = new CmspEdiParser();

        $parsed = new \ReflectionProperty(
            CmspEdiParser::class,
            'parsedData'
        );
        $parsed->setAccessible(true);
        $parsed->setValue($parser, [
            'equipment' => [
                'FULL0000001' => [
                    'full_empty_indicator' => '5',
                ],
            ],
        ]);

        $this->assertFalse(
            $this->invoke(
                $parser,
                'isEmptyContainerItem',
                [[
                    'description' => '',
                    'containers' => ['FULL0000001'],
                ]]
            )
        );
    }

    public function test_empty_unknown_iso_keeps_type_unknown_instead_of_fabricating_one(): void
    {
        $source = file_get_contents(
            base_path('app/Services/Parsers/CmspEdiParser.php')
        );

        $this->assertStringContainsString(
            'if (!$containerType && !$esVacio)',
            $source
        );

        $this->assertStringContainsString(
            "'container_type_id' => \$containerType?->id",
            $source
        );

        $this->assertStringContainsString(
            "if (\$isoCode === '' && !\$esVacio)",
            $source
        );

        $this->assertStringContainsString(
            'contenedor vacío {$containerNumber} sin código ISO; ',
            $source
        );
    }
    public function test_cuscar_source_dates_have_priority_over_form_fallback(): void
    {
        $parser = $this->compatWithoutDatabase();

        $dates = $this->invokeCompat(
            $parser,
            'resolveCuscarOperationalDates',
            [
                [
                    'dates' => [
                        'departure' => '2026-09-20 08:00:00',
                        'estimated_arrival' => '2026-09-21 16:00:00',
                    ],
                ],
                [
                    'departure_date' => '2026-09-19',
                    'discharge_date' => '2026-09-22',
                ],
            ]
        );

        $this->assertSame(
            '2026-09-20 08:00:00',
            $dates['departure_date']
        );
        $this->assertSame(
            '2026-09-21 16:00:00',
            $dates['estimated_arrival_date']
        );

        $fallback = $this->invokeCompat(
            $parser,
            'resolveCuscarOperationalDates',
            [
                ['dates' => []],
                [
                    'departure_date' => '2026-09-19',
                    'discharge_date' => '2026-09-22',
                ],
            ]
        );

        $this->assertSame(
            '2026-09-19',
            $fallback['departure_date']
        );
        $this->assertSame(
            '2026-09-22',
            $fallback['estimated_arrival_date']
        );
    }

}
