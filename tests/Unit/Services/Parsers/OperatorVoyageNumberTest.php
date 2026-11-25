<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\{Port, Voyage};
use App\Services\Parsers\KlineDataParser;
use App\Services\Parsers\KlineDataParserCompat;
use App\Services\Parsers\LoginXmlParserCompat;
use App\Services\Parsers\NavsurTextParserCompat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use Tests\Unit\Services\Parsers\Concerns\BuildsManifestImportSchema;

class OperatorVoyageNumberTest extends TestCase
{
    use BuildsManifestImportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedDatabase();
        Schema::table('vessels', fn (Blueprint $table) => $table->string('operational_status')->nullable());
        $this->addVessel('QA VESSEL');
        DB::table('vessels')->where('id', self::VESSEL)->update(['operational_status' => 'active']);
    }

    public function test_navsur_prefers_and_validates_operator_number(): void
    {
        $parser = new NavsurTextParserCompat();
        $this->assertOperatorPriority(function (array $options) use ($parser): Voyage {
            $this->setOptions($parser, $options);
            return $this->invoke($parser, 'findOrCreateVoyage', [['voyage_number' => 'SOURCE01', 'pol' => 'ARBUE', 'pod' => 'PYASU']]);
        });
    }

    public function test_login_prefers_and_validates_operator_number(): void
    {
        $parser = new LoginXmlParserCompat();
        $this->assertOperatorPriority(function (array $options) use ($parser): Voyage {
            $this->setOptions($parser, $options);
            $data = ['voyage' => ['voyage_number' => 'SOURCE01', 'origin_port' => 'BUENOS AIRES', 'destination_port' => 'ASUNCION', 'departure_date' => null, 'estimated_arrival_date' => null]];
            return $this->invoke($parser, 'createVoyage', [$data, ['company_id' => self::COMPANY, 'user_id' => self::USER]]);
        });
    }

    public function test_kline_prefers_and_validates_operator_number(): void
    {
        $parser = new KlineDataParserCompat();
        $this->assertOperatorPriority(function (array $options) use ($parser): Voyage {
            $ports = Port::whereIn('code', ['ARBUE', 'PYASU'])->get()->keyBy('code');
            return $this->invoke($parser, 'createVoyage', [['voyage_number' => 'SOURCE01'], $ports['ARBUE'], $ports['PYASU'], $options]);
        }, 'KLINE-');
    }

    public function test_kline_base_prefers_and_validates_operator_number(): void
    {
        $parser = new KlineDataParser();
        $this->assertOperatorPriority(function (array $options) use ($parser): Voyage {
            $ports = Port::whereIn('code', ['ARBUE', 'PYASU'])->get()->keyBy('code');
            return $this->invoke($parser, 'createVoyage', [['voyage_number' => 'SOURCE01'], $ports['ARBUE'], $ports['PYASU'], $options]);
        }, 'KLINE-');
    }

    private function setOptions(object $parser, array $options): void
    {
        (new ReflectionProperty($parser, 'importOptions'))->setValue($parser, $options);
    }

    private function invoke(object $parser, string $method, array $arguments): Voyage
    {
        return Model::withoutEvents(fn () => (new ReflectionMethod($parser, $method))->invokeArgs($parser, $arguments));
    }

    private function assertOperatorPriority(callable $create, string $prefix = ''): void
    {
        $options = ['vessel_id' => self::VESSEL];
        $this->assertSame($prefix . 'OPERATOR01', $create($options + ['voyage_number' => ' OPERATOR01 '])->fresh()->voyage_number);
        $this->assertSame($prefix . 'SOURCE01', $create($options + ['voyage_number' => '  '])->fresh()->voyage_number);

        $this->expectExceptionMessage('El viaje ' . $prefix . 'OPERATOR01 ya existe');
        $create($options + ['voyage_number' => 'OPERATOR01']);
    }
}
