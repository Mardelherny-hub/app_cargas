<?php

namespace Tests\Unit\Services\Parsers;

use App\Services\Parsers\KlineDataParser;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class KlineDataParserContractTest extends TestCase
{
    private KlineDataParser $parser;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new KlineDataParser();
        $this->reflection = new ReflectionClass($this->parser);
    }

    private function call(string $method, array $args = []): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->parser, $args);
    }

    public function test_kline_contract_for_ncm_volume_marks_and_freight(): void
    {
        $cases = [
            'KKLUCTG001009' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000043UNITS 00595580000KGS000580070M3',
                    ],
                    'DESCREC0' => [
                        '021001HS CODE: 87.03.22',
                    ],
                    'MARKREC0' => [
                        '001001HS CODE: 87.03.22',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000043000000000000646140UNT USD G000027784020COBOG',
                    ],
                ],
                'ncm' => '87032200',
                'volume' => 580.07,
                'marks' => 'SM',
                'freight' => 27784.02,
            ],

            'KKLUCTG001010' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000002UNITS 00027860000KGS000026980M3',
                    ],
                    'DESCREC0' => [
                        '021001HS CODE: 87.03.22',
                    ],
                    'MARKREC0' => [
                        '001001HS CODE: 87.03.22',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000002000000000000646140UNT USD G000001292280COBOG',
                    ],
                ],
                'ncm' => '87032200',
                'volume' => 26.98,
                'marks' => 'SM',
                'freight' => 1292.28,
            ],

            'KKLUCTG001011' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000002UNITS 00026360000KGS000026980M3',
                    ],
                    'DESCREC0' => [
                        '020001HS CODE: 87.03.22 - 87.03.23',
                    ],
                    'MARKREC0' => [
                        '001001HS CODE: 87.03.22 -',
                        '00200187.03.23',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000002000000000000646140UNT USD G000001292280COBOG',
                    ],
                ],
                'ncm' => '87032200',
                'volume' => 26.98,
                'marks' => 'SM',
                'freight' => 1292.28,
            ],

            'KKLUCTG001012' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000437UNITS 06034080000KGS005895130M3',
                    ],
                    'DESCREC0' => [
                        '031001HS CODE: 87.03.22 - 87.03.23',
                    ],
                    'MARKREC0' => [
                        '001001HS CODE: 87.03.22 -',
                        '00200187.03.23',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000437000000000000646140UNT USD G000282363180COBOG',
                    ],
                ],
                'ncm' => '87032200',
                'volume' => 5895.13,
                'marks' => 'SM',
                'freight' => 282363.18,
            ],

            'KKLU695004691' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000572VEHICLES 06661940000KGS006743880M3 87032100',
                    ],
                    'DESCREC0' => [
                        '021001NCM: 87.03.2100 / 8703.23.10',
                        '022001NET WEIGHT: 666.194,00 KGS',
                        '023001M3: 6.743,88',
                    ],
                    'MARKREC0' => [
                        '001001RENAULT - ORIGEN -',
                        '002001BRASIL',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000572000000000000373790UNT USD G000213807880BRPNG',
                    ],
                ],
                'ncm' => '87032100',
                'volume' => 6743.88,
                'marks' => 'RENAULT - ORIGEN - / BRASIL',
                'freight' => 213807.88,
            ],

            'KKLU695004692' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000214VEHICLES 04314190000KGS003092300M3 87043190',
                    ],
                    'DESCREC0' => [
                        '013001NCM: 87.04.3190',
                        '015001NET WEIGHT: 431.419,00 KGS',
                        '016001M3: 3.092,30',
                    ],
                    'MARKREC0' => [
                        '001001RENAULT - ORIGEN -',
                        '002001BRASIL',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000214000000000000376900UNT USD G000080656600BRPNG',
                    ],
                ],
                'ncm' => '87043190',
                'volume' => 3092.30,
                'marks' => 'RENAULT - ORIGEN - / BRASIL',
                'freight' => 80656.60,
            ],

            'KKLU695004693' => [
                'data' => [
                    'CMMDREC0' => [
                        '000001NAUT00000185VEHICLES 01517000000KGS001585450M3 87032100',
                    ],
                    'DESCREC0' => [
                        '013001NCM: 87.03.2100',
                        '014001NET WEIGHT: 151.700,00 KGS',
                        '015001M3: 1.585,45',
                    ],
                    'MARKREC0' => [
                        '001001RENAULT - ORIGEN -',
                        '002001BRASIL',
                    ],
                    'FRTCREC0' => [
                        '001POFT 0000185000000000000370030UNT USD G000068455550BRPNG',
                    ],
                ],
                'ncm' => '87032100',
                'volume' => 1585.45,
                'marks' => 'RENAULT - ORIGEN - / BRASIL',
                'freight' => 68455.55,
            ],
        ];

        foreach ($cases as $bl => $case) {
            $data = $case['data'];

            $measurements = $this->call(
                'extractRealMeasurements',
                [$data]
            );

            $ncm = $this->call(
                'extractNCMCode',
                [$data]
            );

            $marks = $this->call(
                'extractCargoMarks',
                [$data]
            );

            $terms = $this->call(
                'extractFreightTerms',
                [$data]
            );

            $freight = $this->call(
                'extractFreightCharges',
                [$data, $terms]
            );

            $this->assertSame(
                $case['ncm'],
                $ncm,
                "{$bl}: NCM incorrecto"
            );

            $this->assertEqualsWithDelta(
                $case['volume'],
                $measurements['volume_m3'],
                0.001,
                "{$bl}: volumen incorrecto"
            );

            $this->assertSame(
                $case['marks'],
                $marks,
                "{$bl}: marcas incorrectas"
            );

            $this->assertEqualsWithDelta(
                $case['freight'],
                $freight['amount'],
                0.001,
                "{$bl}: importe de flete incorrecto"
            );
        }
    }
}
