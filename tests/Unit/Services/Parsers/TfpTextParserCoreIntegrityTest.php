<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Port;
use App\Services\Parsers\TfpTextParser;
use ReflectionMethod;
use Tests\TestCase;

class TfpTextParserCoreIntegrityTest extends TestCase
{
    private TfpTextParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = app(TfpTextParser::class);
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod(TfpTextParser::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($this->parser, $args);
    }

    public function test_voyage_key_is_deterministic(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tfp-');
        file_put_contents($file, 'fixture');

        $block = '
            BUQUE: /*REINA DEL PARANA*/
            CODPUERTOCARGA: /*ARBAI*/
            CODPUERTODESCARGA: /*PYPSE*/
        ';

        $data = $this->invoke('extractVoyageData', [$block, $file]);

        $this->assertSame(
            'TFP-' . substr(hash_file('sha256', $file), 0, 16),
            $data['voyage_number']
        );

        unlink($file);
    }

    public function test_route_defines_voyage_cargo_type(): void
    {
        $ar = Port::where('code', 'ARBUE')->firstOrFail();
        $py = Port::where('code', 'PYPSE')->firstOrFail();

        $this->assertSame(
            'export',
            $this->invoke('resolveTfpVoyageCargoType', [$ar, $py])
        );

        $this->assertSame(
            'import',
            $this->invoke('resolveTfpVoyageCargoType', [$py, $ar])
        );
    }

    public function test_real_container_types_are_preserved(): void
    {
        $this->assertSame(
            '20GP',
            $this->invoke('findOrCreateContainerType', ['20DV'])->code
        );

        $this->assertSame(
            '40HC',
            $this->invoke('findOrCreateContainerType', ['40HC'])->code
        );
    }

    public function test_unknown_container_type_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke('findOrCreateContainerType', ['40FR']);
    }

    public function test_tfp_condition_p_is_preserved_for_afip(): void
    {
        $this->assertSame(
            ['condition' => 'L', 'container_condition' => 'P'],
            $this->invoke('mapTfpCondition', ['P'])
        );
    }

    public function test_unknown_condition_is_rejected(): void
    {
        $this->expectException(\Exception::class);

        $this->invoke('mapTfpCondition', ['X']);
    }
}
