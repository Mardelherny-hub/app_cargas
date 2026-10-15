<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\Shipment;
use App\Services\Simple\SimpleXmlGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class ArgentinaMicDtaEmptyTitleXmlTest extends TestCase
{
    public function test_empty_container_title_with_real_party_relations_generates_xml(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        Http::preventStrayRequests();

        Schema::create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('alpha2_code');
        });
        Schema::create('ports', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->unsignedBigInteger('country_id');
        });
        Schema::create('voyages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('origin_port_id');
            $t->unsignedBigInteger('destination_port_id');
            $t->nullableMorphs('unused_owner');
        });
        // Tablas mínimas de las relaciones efectivas del sistema.
        // Datos de prueba exclusivos de SQLite en memoria.
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('country_id');
            $t->string('legal_name');
            $t->string('commercial_name')->nullable();
            $t->string('tax_id');
        });
        Schema::create('client_contact_data', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->boolean('is_primary');
            $t->string('address_line_1');
            $t->string('address_line_2')->nullable();
            $t->string('city')->nullable();
            $t->string('state_province')->nullable();
            $t->string('postal_code')->nullable();
        });
        Schema::create('bill_of_lading_contacts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('bill_of_lading_id');
            $t->unsignedBigInteger('client_contact_data_id')->nullable();
            $t->string('role');
            $t->boolean('use_specific_data')->default(false);
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('voyage_id');
            $t->string('shipment_number');
        });
        Schema::create('bills_of_lading', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->string('bill_number');
            $t->softDeletes();
            $t->unsignedBigInteger('shipper_id')->nullable();
            $t->unsignedBigInteger('consignee_id')->nullable();
            $t->unsignedBigInteger('notify_party_id')->nullable();
        });
        Schema::create('shipment_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('bill_of_lading_id');
        });
        Schema::create('containers', function (Blueprint $t) {
            $t->id();
            $t->string('container_number');
            $t->string('condition');
            $t->string('container_condition');
            $t->string('argentina_container_code');
            $t->string('shipper_seal');
        });
        Schema::create('container_shipment_item', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('container_id');
            $t->unsignedBigInteger('shipment_item_id');
            foreach (['package_quantity', 'gross_weight_kg', 'net_weight_kg', 'volume_m3', 'quantity_percentage', 'weight_percentage', 'volume_percentage', 'verified_gross_mass_kg'] as $name) {
                $t->decimal($name)->nullable();
            }
            foreach (['loaded_at', 'sealed_at', 'source_seals', 'source_line_numbers', 'container_condition', 'status'] as $name) {
                $t->string($name)->nullable();
            }
            $t->integer('loading_sequence')->nullable();
            $t->timestamps();
        });
        Schema::create('afip_operative_locations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('port_id');
            $t->boolean('is_active');
            $t->string('location_code');
        });

        DB::table('countries')->insert([['id' => 1, 'alpha2_code' => 'AR'], ['id' => 2, 'alpha2_code' => 'PY']]);
        DB::table('ports')->insert([['id' => 1, 'code' => 'ARBUE', 'country_id' => 1], ['id' => 2, 'code' => 'PYTVT', 'country_id' => 2]]);
        DB::table('voyages')->insert(['id' => 7, 'origin_port_id' => 1, 'destination_port_id' => 2]);
        DB::table('clients')->insert([
            ['id' => 201, 'country_id' => 1, 'legal_name' => 'QA REMITENTE', 'tax_id' => '30111111111'],
            ['id' => 202, 'country_id' => 2, 'legal_name' => 'QA CONSIGNATARIO', 'tax_id' => '30222222222'],
        ]);
        DB::table('client_contact_data')->insert([
            ['id' => 301, 'client_id' => 201, 'is_primary' => true, 'address_line_1' => 'CALLE QA REMITENTE 10'],
            ['id' => 302, 'client_id' => 202, 'is_primary' => true, 'address_line_1' => 'CALLE QA CONSIGNATARIO 20'],
        ]);
        DB::table('shipments')->insert(['id' => 11, 'voyage_id' => 7, 'shipment_number' => 'QA-EMPTY']);
        DB::table('bills_of_lading')->insert([
            'id' => 31, 'shipment_id' => 11, 'bill_number' => 'VACIOS',
            'shipper_id' => 201, 'consignee_id' => 202,
        ]);
        DB::table('shipment_items')->insert(['id' => 41, 'bill_of_lading_id' => 31]);
        DB::table('containers')->insert([
            'id' => 51, 'container_number' => 'SEGU7679464', 'condition' => 'V',
            'container_condition' => 'H', 'argentina_container_code' => '40FL', 'shipper_seal' => 'QA-SEAL',
        ]);
        DB::table('container_shipment_item')->insert(['container_id' => 51, 'shipment_item_id' => 41]);
        DB::table('afip_operative_locations')->insert(['port_id' => 1, 'is_active' => 1, 'location_code' => 'QA001']);

        $company = (new Company())->forceFill(['id' => 1, 'tax_id' => '30700000001', 'ws_environment' => 'testing']);
        $generator = new SimpleXmlGenerator($company);
        (new ReflectionProperty($generator, 'preparedWsaaTokens'))->setValue($generator, [
            'wgesregsintia2' => ['token' => 'QA-TOKEN', 'sign' => 'QA-SIGN', 'cuit' => '30700000001'],
        ]);
        $xml = $generator->createRegistrarTitEnviosXml(Shipment::findOrFail(11), 'QA-EMPTY-001');
        $document = new \DOMDocument();
        // El namespace oficial es un identificador no absoluto; libxml
        // advierte, pero la salida sigue siendo XML parseable.
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadXML($xml));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[local-name()="TitTransContVacio"]')->length);
        $this->assertSame(0, $xpath->query('//*[local-name()="TitTransEnvio"]')->length);
        $this->assertSame('VACIOS', $xpath->evaluate('string(//*[local-name()="TitTransContVacio"]/*[local-name()="idTitTrans"])'));
        $this->assertSame('SEGU7679464', $xpath->evaluate('string(//*[local-name()="TitTransContVacio"]//*[local-name()="idCont"])'));
        // AFIP 41933 (transacción 505): las tres partes deben existir en
        // TitTransContVacio. Se toman de las relaciones reales del BL;
        // destinatario utiliza el consignatario según el generador actual.
        foreach ([
            'remitente' => ['QA REMITENTE', 'CALLE QA REMITENTE 10', '30111111111'],
            'consignatario' => ['QA CONSIGNATARIO', 'CALLE QA CONSIGNATARIO 20', '30222222222'],
            'destinatario' => ['QA CONSIGNATARIO', 'CALLE QA CONSIGNATARIO 20', null],
        ] as $party => [$name, $address, $taxId]) {
            $path = '//*[local-name()="TitTransContVacio"]/*[local-name()="' . $party . '"]';
            $this->assertSame(1, $xpath->query($path)->length, "{$party} ausente.");
            $this->assertSame($name, $xpath->evaluate('string(' . $path . '/*[local-name()="nomRazSoc"])'));
            $this->assertSame($address, $xpath->evaluate(
                'string(' . $path . '/*[local-name()="domicilio"]/*[local-name()="nombreCalle"])'
            ));
            if ($taxId !== null) {
                $this->assertSame($taxId, $xpath->evaluate(
                    'string(' . $path . '/*[local-name()="idFiscal"])'
                ));
            }
        }
        $this->assertSame(0, $xpath->query(
            '//*[local-name()="TitTransContVacio"]/*[local-name()="notificado"]'
        )->length);
        $this->assertSame('30700000001', $xpath->evaluate('string(//*[local-name()="TitTransContVacio"]/*[local-name()="idFiscalATAMIC"])'));
    }
}
