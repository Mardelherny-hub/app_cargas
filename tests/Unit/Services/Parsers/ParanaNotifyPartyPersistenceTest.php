<?php

namespace Tests\Unit\Services\Parsers;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\Port;
use App\Models\Shipment;
use App\Models\Voyage;
use App\Services\Parsers\ParanaExcelParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ParanaNotifyPartyPersistenceTest extends TestCase
{
    public function test_imported_notify_party_survives_database_reload(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('legal_name');
        });
        Schema::create('client_contact_data', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->boolean('is_primary')->default(false);
            $t->string('address_line_1')->nullable();
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->string('bill_number');
            $t->unsignedBigInteger('shipper_id');
            $t->unsignedBigInteger('consignee_id');
            $t->unsignedBigInteger('notify_party_id')->nullable();
            foreach (['bill_date', 'loading_date'] as $name) { $t->date($name)->nullable(); }
            foreach (['master_bill_number', 'permiso_embarque', 'commodity_code', 'cargo_marks'] as $name) { $t->string($name)->nullable(); }
            $t->string('cargo_description');
            $t->string('freight_terms')->nullable();
            $t->string('status')->nullable();
            $t->unsignedBigInteger('loading_port_id');
            $t->unsignedBigInteger('discharge_port_id');
            $t->unsignedBigInteger('primary_cargo_type_id');
            $t->unsignedBigInteger('primary_packaging_type_id');
            $t->decimal('gross_weight_kg')->nullable();
            $t->decimal('net_weight_kg')->nullable();
            $t->integer('total_packages')->nullable();
            $t->decimal('volume_m3')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        DB::table('clients')->insert([
            ['id' => 1, 'legal_name' => 'SHIPPER QA'],
            ['id' => 2, 'legal_name' => 'CONSIGNEE QA'],
            ['id' => 3, 'legal_name' => 'MSC ARGENTINA'],
        ]);

        $parser = new class extends ParanaExcelParser {
            protected function resolvePortStrict(?string $code): Port
            {
                return (new Port())->forceFill(['id' => $code === 'ARBUE' ? 1 : 2, 'country_id' => 1]);
            }
            protected function findOrCreateClient(array $data, int $companyId, int $countryId): ?Client
            {
                return Client::query()->where('legal_name', $data['name'])->first();
            }
            protected function determinateCargoTypeId(array $data): int { return 1; }
            protected function determinatePackagingTypeId(array $data): int { return 1; }
            protected function persistClientAddress(?Client $client, ?string $address): bool { return false; }
            protected function resolveSpecificAddress(?Client $client, ?string $address, string $role): ?array { return null; }
        };
        $voyage = (new Voyage())->forceFill(['id' => 1, 'company_id' => 1008]);
        $shipment = (new Shipment())->forceFill(['id' => 11, 'voyage_id' => 1]);
        $shipment->setRelation('voyage', $voyage);
        $data = [
            'BL_NUMBER' => 'QA-BL-PARANA',
            'BL_DATE' => null,
            'POL' => 'ARBUE', 'POD' => 'PYTVT',
            'SHIPPER_NAME' => 'SHIPPER QA',
            'CONSIGNEE_NAME' => 'CONSIGNEE QA',
            'NOTIFY_PARTY_NAME' => 'MSC ARGENTINA',
            'NOTIFY_PARTY_ADDRESS1' => 'CUIT 30-69318494-7',
            'NOTIFY_PARTY_COUNTRY' => 'ARGENTINA',
            'DESCRIPTION' => 'CARGA QA',
            'GROSS_WEIGHT' => '100',
            'NET_WEIGHT' => '90',
            'NUMBER_OF_PACKAGES' => '1',
            'VOLUME' => '1',
        ];

        $created = (new ReflectionMethod($parser, 'createBillOfLading'))->invoke($parser, $shipment, $data);
        $this->assertSame(3, (int) $created->notify_party_id);
        $saved = BillOfLading::query()->findOrFail($created->id);
        $this->assertSame('QA-BL-PARANA', $saved->bill_number);
        $this->assertSame(3, (int) $saved->notify_party_id);
    }
}
