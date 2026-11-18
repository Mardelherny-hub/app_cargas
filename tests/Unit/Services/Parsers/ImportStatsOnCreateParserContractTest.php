<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\Voyage;
use App\Services\Parsers\CmspEdiParser;
use App\Services\Parsers\Concerns\RecalculatesNewVoyageTotals;
use App\Services\Parsers\DonToyoExcelParser;
use App\Services\Parsers\GuaranExcelParser;
use App\Services\Parsers\LoginXmlParser;
use App\Services\Parsers\MscVesselNameParser;
use App\Services\Parsers\NavsurTextParser;
use App\Services\Parsers\ParanaExcelParser;
use App\Services\Parsers\TfpTextParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ImportStatsOnCreateParserContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('voyages', function (Blueprint $table) {
            $table->id();
            $table->boolean('active');
            $table->decimal('total_cargo_weight', 12, 2);
            $table->decimal('total_cargo_weight_loaded', 10, 2);
            $table->integer('total_containers');
            $table->integer('total_containers_loaded');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voyage_id');
            $table->boolean('active');
            $table->decimal('cargo_weight_loaded', 10, 2);
            $table->integer('containers_loaded');
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('bills_of_lading', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->decimal('gross_weight_kg', 12, 2);
            $table->softDeletes();
        });
        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_of_lading_id');
            $table->decimal('gross_weight_kg', 12, 2)->nullable();
        });
        Schema::create('container_shipment_item', function (Blueprint $table) {
            $table->unsignedBigInteger('container_id');
            $table->unsignedBigInteger('shipment_item_id');
        });
    }

    public static function parsers(): array
    {
        return [
            'CMSP' => [CmspEdiParser::class, 'createModelObjects', '$this->createContainersAndItemsForGroup(', '$targetVoyage', false],
            'TFP' => [TfpTextParser::class, 'parse', '$this->attachContainerToItem(', '$targetVoyage', false],
            'Don Toyo' => [DonToyoExcelParser::class, 'parse', "DB::table('container_shipment_item')->insert(", '$isTarget', true],
            'Login' => [LoginXmlParser::class, 'parseWithContext', '$this->createShipmentItemFromBillData(', '$this->importingToTargetVoyage', false],
            'Navsur' => [NavsurTextParser::class, 'parse', '$this->attachContainerToItem(', '$targetVoyage', false],
            'Parana' => [ParanaExcelParser::class, 'parse', '$this->createShipmentItem(', '$targetVoyage', false],
            'Guaran' => [GuaranExcelParser::class, 'parse', '$this->attachContainerToItem(', '$targetVoyage', false],
            'MSC' => [MscVesselNameParser::class, 'parse', "DB::table('container_shipment_item')->insert(", '$targetVoyage', true],
        ];
    }

    #[DataProvider('parsers')]
    public function test_new_voyage_totals_contract(string $class, string $method, string $insertion, string $target, bool $elseBranch): void
    {
        $reflection = new ReflectionClass($class);
        $this->assertArrayHasKey(RecalculatesNewVoyageTotals::class, class_uses_recursive($class));
        $this->assertTrue($reflection->hasMethod('recalculateNewVoyageTotals'));
        $calculator = $reflection->getMethod('recalculateNewVoyageTotals');
        $this->assertTrue($calculator->isProtected());
        $source = $this->methodTokens($reflection, $method);
        $call = '$this->recalculateNewVoyageTotals($voyage);';
        $this->assertSame(1, substr_count($source, $call));
        $this->assertNotFalse(strpos($source, $insertion));
        $this->assertLessThan(strpos($source, $call), strpos($source, $insertion));
        if ($elseBranch) {
            $this->assertCallInElse($source, $target, $call);
        } else {
            $this->assertStringContainsString('if(!'.$target.'){'.$call.'}', $source);
        }
        if ($method !== 'parse') {
            $this->assertStringContainsString('$this->'.$method.'(', $this->methodTokens($reflection, 'parse'));
        }

        $timestamp = '2020-01-02 03:04:05';
        DB::table('voyages')->insert([
            ['id' => 1, 'active' => 1, 'total_cargo_weight' => 0, 'total_cargo_weight_loaded' => 0, 'total_containers' => 0, 'total_containers_loaded' => 0, 'updated_at' => $timestamp],
            ['id' => 2, 'active' => 1, 'total_cargo_weight' => 88, 'total_cargo_weight_loaded' => 88, 'total_containers' => 8, 'total_containers_loaded' => 8, 'updated_at' => $timestamp],
        ]);
        DB::table('shipments')->insert([
            ['id' => 1, 'voyage_id' => 1, 'active' => 1, 'cargo_weight_loaded' => 0, 'containers_loaded' => 0, 'updated_at' => $timestamp],
            ['id' => 2, 'voyage_id' => 2, 'active' => 1, 'cargo_weight_loaded' => 88, 'containers_loaded' => 8, 'updated_at' => $timestamp],
        ]);
        DB::table('bills_of_lading')->insert([
            ['id' => 1, 'shipment_id' => 1, 'gross_weight_kg' => 999, 'deleted_at' => null],
            ['id' => 2, 'shipment_id' => 1, 'gross_weight_kg' => 999, 'deleted_at' => null],
            ['id' => 3, 'shipment_id' => 2, 'gross_weight_kg' => 999, 'deleted_at' => null],
        ]);
        DB::table('shipment_items')->insert([
            ['id' => 1, 'bill_of_lading_id' => 1, 'gross_weight_kg' => 0.10],
            ['id' => 2, 'bill_of_lading_id' => 1, 'gross_weight_kg' => 0.20],
            ['id' => 3, 'bill_of_lading_id' => 2, 'gross_weight_kg' => 1.23],
            ['id' => 4, 'bill_of_lading_id' => 3, 'gross_weight_kg' => 99],
        ]);
        DB::table('container_shipment_item')->insert([
            ['container_id' => 7, 'shipment_item_id' => 1],
            ['container_id' => 7, 'shipment_item_id' => 2],
            ['container_id' => 8, 'shipment_item_id' => 3],
            ['container_id' => 9, 'shipment_item_id' => 4],
        ]);
        $foreignVoyage = DB::table('voyages')->where('id', 2)->first();
        $foreignShipment = DB::table('shipments')->where('id', 2)->first();
        $voyage = new Voyage();
        $voyage->id = 1;
        $calculator->invoke($reflection->newInstance(), $voyage);
        $shipment = DB::table('shipments')->where('id', 1)->first();
        $result = DB::table('voyages')->where('id', 1)->first();
        $this->assertEquals(1.53, $shipment->cargo_weight_loaded);
        $this->assertSame(2, $shipment->containers_loaded);
        $this->assertEquals(1.53, $result->total_cargo_weight);
        $this->assertEquals(1.53, $result->total_cargo_weight_loaded);
        $this->assertSame(2, $result->total_containers);
        $this->assertSame(2, $result->total_containers_loaded);
        $this->assertSame($timestamp, $shipment->updated_at);
        $this->assertSame($timestamp, $result->updated_at);
        $this->assertEquals($foreignVoyage, DB::table('voyages')->where('id', 2)->first());
        $this->assertEquals($foreignShipment, DB::table('shipments')->where('id', 2)->first());
    }

    private function methodTokens(ReflectionClass $class, string $method): string
    {
        $reflection = $class->getMethod($method);
        $lines = file($reflection->getFileName());
        $source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        $tokens = token_get_all('<?php '.$source);

        return implode('', array_map(static fn ($token) => is_array($token) ? $token[1] : $token, array_filter($tokens, static fn ($token) => !is_array($token) || !in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))));
    }

    private function assertCallInElse(string $source, string $target, string $call): void
    {
        $marker = 'if('.$target.'){';
        $position = 0;
        $found = false;
        while (($start = strpos($source, $marker, $position)) !== false) {
            $open = $start + strlen($marker) - 1;
            $close = $this->closingBrace($source, $open);
            $position = $close + 1;
            if (substr($source, $position, 5) !== 'else{') {
                continue;
            }
            $elseOpen = $position + 4;
            $elseClose = $this->closingBrace($source, $elseOpen);
            $targetBody = substr($source, $open + 1, $close - $open - 1);
            $elseBody = substr($source, $elseOpen + 1, $elseClose - $elseOpen - 1);
            if (str_contains($elseBody, $call)) {
                $this->assertStringNotContainsString($call, $targetBody);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The recalculation must be in the new-voyage else branch.');
    }

    private function closingBrace(string $source, int $open): int
    {
        $depth = 0;
        for ($i = $open, $length = strlen($source); $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}' && --$depth === 0) {
                return $i;
            }
        }
        $this->fail('Unbalanced source braces.');
    }
}
