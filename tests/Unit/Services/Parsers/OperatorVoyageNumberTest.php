<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Voyage;
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
