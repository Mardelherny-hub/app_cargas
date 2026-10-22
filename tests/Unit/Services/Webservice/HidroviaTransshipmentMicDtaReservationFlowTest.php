<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\HidroviaTransshipmentAllocation;
use App\Models\User;
use App\Models\Voyage;
use App\Models\WebserviceTrack;
use App\Models\WebserviceTransaction;
use App\Services\Simple\ArgentinaMicDtaService;
use App\Services\Simple\HidroviaTransshipmentRecordedTrackService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;
use SoapClient;
use Tests\TestCase;

/**
 * Contrato integral LOCAL: reserva -> respuesta registrada -> TRACK.
 * La respuesta ARCA y la autenticación son sintéticas: no certifica SOAP.
 * No hay WSDL remoto, WSAA, transmisión ni datos de producción.
 */
class HidroviaTransshipmentMicDtaReservationFlowTest extends TestCase
{
    private string $previousConnection;
    private Voyage $voyage;
    private ArgentinaMicDtaService $micDta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConnection = config('database.default');
        config([
            'database.default' => 'hidrovia_reservation_sqlite',
            'database.connections.hidrovia_reservation_sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:',
                'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'logging.default' => 'null',
            'audit.enabled' => false,
        ]);
        $schema = Schema::connection('hidrovia_reservation_sqlite');
        $schema->create('users', fn (Blueprint $t) => $t->id());
        $schema->create('voyages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id');
        });
        $schema->create('vessel_types', function (Blueprint $t) {
            $t->id(); $t->string('category');
        });
        $schema->create('vessels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vessel_type_id');
        });
        $schema->create('shipments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('voyage_id');
            $t->unsignedBigInteger('vessel_id'); $t->string('shipment_number');
        });
        $schema->create('bills_of_lading', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id');
            $t->string('bill_number'); $t->string('status');
            $t->string('is_transit_transshipment'); $t->string('id_decla');
            $t->boolean('is_fractional')->default(false); $t->softDeletes();
        });
        $schema->create('shipment_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('bill_of_lading_id');
            $t->integer('line_number'); $t->integer('package_quantity');
            $t->decimal('gross_weight_kg', 12, 2);
            $t->string('packaging_code'); $t->string('item_description');
            $t->string('cargo_marks')->nullable();
            $t->string('imdg_class')->nullable();
            $t->boolean('is_dangerous_goods')->default(false);
        });
        $schema->create('containers', fn (Blueprint $t) => $t->id());
        $schema->create('container_shipment_item', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_item_id');
            $t->unsignedBigInteger('container_id');
            foreach (['package_quantity', 'gross_weight_kg', 'net_weight_kg', 'volume_m3',
                'quantity_percentage', 'weight_percentage', 'volume_percentage',
                'verified_gross_mass_kg'] as $field) $t->decimal($field)->nullable();
            foreach (['loaded_at', 'sealed_at', 'loading_sequence', 'source_seals',
                'source_line_numbers', 'container_condition', 'status'] as $field) {
                $t->string($field)->nullable();
            }
            $t->timestamps();
        });
        foreach ([
            '2025_07_23_120939_create_webservice_transactions_table.php',
            '2025_09_08_122553_create_webservice_tracks_table.php',
            '2026_10_19_123000_create_hidrovia_transshipment_allocations_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        DB::table('users')->insert(['id' => 1]);
        DB::table('voyages')->insert([['id' => 7, 'company_id' => 101],
            ['id' => 8, 'company_id' => 202]]);
        DB::table('vessel_types')->insert(['id' => 301, 'category' => 'barge']);
        DB::table('vessels')->insert([['id' => 201, 'vessel_type_id' => 301],
            ['id' => 202, 'vessel_type_id' => 301]]);
        DB::table('shipments')->insert([['id' => 11, 'voyage_id' => 7,
            'vessel_id' => 201, 'shipment_number' => 'BAR-1'],
            ['id' => 12, 'voyage_id' => 7,
                'vessel_id' => 202, 'shipment_number' => 'BAR-2']]);
        DB::table('bills_of_lading')->insert(['id' => 31, 'shipment_id' => 11,
            'bill_number' => 'QA-BL-31', 'id_decla' => 'QA-DECL',
            'status' => 'draft', 'is_transit_transshipment' => 'S']);
        DB::table('shipment_items')->insert(['id' => 41, 'bill_of_lading_id' => 31,
            'line_number' => 2, 'package_quantity' => 10, 'gross_weight_kg' => '100.25',
            'packaging_code' => 'BX', 'item_description' => 'MERCADERIA QA',
            'cargo_marks' => 'MARCA QA']);
        DB::table('hidrovia_transshipment_allocations')->insert([
            ['bill_of_lading_id' => 31, 'shipment_item_id' => 41,
                'target_shipment_id' => 11, 'package_quantity' => 4,
                'gross_weight_kg' => '40.10'],
            ['bill_of_lading_id' => 31, 'shipment_item_id' => 41,
                'target_shipment_id' => 12, 'package_quantity' => 6,
                'gross_weight_kg' => '60.15'],
        ]);
        $this->voyage = Voyage::findOrFail(7);
        $company = (new Company())->forceFill(['id' => 101, 'ws_environment' => 'testing']);
        $user = (new User())->forceFill(['id' => 1]);
        $this->micDta = new class($company, $user) extends ArgentinaMicDtaService {
            protected function createSoapClient(): SoapClient
            {
                throw new LogicException('PROHIBIDO llamar SOAP en QA de reserva.');
            }
        };
    }

    protected function tearDown(): void
    {
        DB::purge('hidrovia_reservation_sqlite');
        config(['database.default' => $this->previousConnection]);
        parent::tearDown();
    }

    private function template(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>
<RegistrarTitEnvios xmlns="Ar.Gob.Afip.Dga.wgesregsintia2">
<argWSAutenticacionEmpresa><Token>QA-TOKEN</Token><Sign>QA-SIGN</Sign><CuitEmpresaConectada>30700000001</CuitEmpresaConectada><TipoAgente>TRSP</TipoAgente><Rol>TRSP</Rol></argWSAutenticacionEmpresa>
<argRegistrarTitEnviosParam><idTransaccion>QA-TX-001</idTransaccion><titulosTransEnvios><TitTransEnvio>
<codViaTrans>8</codViaTrans><idTitTrans>QA-BL-31</idTitTrans><obsDeclaAduInter>OBS QA</obsDeclaAduInter>
<remitente><nomRazSoc>REM QA</nomRazSoc></remitente><consignatario><nomRazSoc>CON QA</nomRazSoc></consignatario><destinatario><nomRazSoc>DEST QA</nomRazSoc></destinatario>
<indFinCom>S</indFinCom><indFraccTransp>N</indFraccTransp><indConsol>N</indConsol>
<origen><codAdu>001</codAdu></origen><destino><codPais>PY</codPais><codAdu>002</codAdu></destino>
<envios><Envio><destinaciones><Destinacion>
<idDecla>QA-DECL</idDecla><montoFob>0.0000</montoFob><montoFlete>0.0000</montoFlete><montoSeg>0.0000</montoSeg>
<codDivisaFob>USD</codDivisaFob><codDivisaFle>USD</codDivisaFle><codDivisaSeg>USD</codDivisaSeg>
<items><Item><nroItem>2</nroItem><peso>100.2500</peso></Item></items>
<docAnexos><DocAnexo><codTipDoc>380</codTipDoc><documento>QA-INV</documento></DocAnexo></docAnexos>
<bultos><Bulto><cantBultos>10</cantBultos><pesoBruto>100.2500</pesoBruto></Bulto></bultos>
</Destinacion></destinaciones><indUltFra>S</indUltFra><idFiscalATAMIC>30700000001</idFiscalATAMIC>
<lugOperOrigen><codLugOper>999</codLugOper><codCiu>ARBUE</codCiu></lugOperOrigen>
<lugOperDestino><codLugOper>888</codLugOper><codCiu>PYTVT</codCiu></lugOperDestino><idEnvio>1</idEnvio></Envio></envios>
</TitTransEnvio></titulosTransEnvios></argRegistrarTitEnviosParam>
</RegistrarTitEnvios></SOAP-ENV:Body></SOAP-ENV:Envelope>
XML;
    }

    private function customs(): array
    {
        return [
            11 => ['idFiscalATAMIC' => '20123456789',
                'lugOperOrigen' => ['codLugOper' => '12345', 'codCiu' => 'ARSFE'],
                'lugOperDestino' => ['codLugOper' => '1234567', 'codCiu' => 'ARROS']],
            12 => ['idFiscalATAMIC' => '20987654321',
                'lugOperOrigen' => ['codLugOper' => 'A123', 'codCiu' => 'ARPNA'],
                'lugOperDestino' => ['codLugOper' => 'B123', 'codCiu' => 'ARROS']],
        ];
    }

    private function reserve(): WebserviceTransaction
    {
        return $this->micDta->reserveHidroviaTitEnviosForReview(
            $this->voyage, 31, $this->template(), $this->customs()
        );
    }

    private function response(string $errorCode = '0'): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<s:Body><RegistrarTitEnviosResponse><RegistrarTitEnviosResult>'
            . '<ListaErrores><DetalleError><Codigo>' . $errorCode . '</Codigo></DetalleError></ListaErrores>'
            . '<titTracksEnv><TitTrackEnv><idTitTrans>QA-BL-31</idTitTrans><tracksEnv>'
            . '<TrackEnv><idTrack>2026AR0000000022</idTrack><idEnvio>2</idEnvio></TrackEnv>'
            . '<TrackEnv><idTrack>2026AR0000000011</idTrack><idEnvio>1</idEnvio></TrackEnv>'
            . '</tracksEnv></TitTrackEnv></titTracksEnv>'
            . '</RegistrarTitEnviosResult></RegistrarTitEnviosResponse></s:Body></s:Envelope>';
    }

    public function test_reservation_is_only_review_and_has_no_soap_or_retry(): void
    {
        $transaction = $this->reserve();
        $this->assertSame('QA-TX-001', $transaction->transaction_id);
        $this->assertSame('validating', $transaction->status);
        $this->assertTrue($transaction->requires_manual_review);
        $this->assertSame(0, $transaction->max_retries);
        $this->assertSame(0, $transaction->retry_count);
        $this->assertNull($transaction->next_retry_at);
        $this->assertNull($transaction->sent_at);
        $this->assertNull($transaction->response_xml);
        $this->assertSame('micdta', $transaction->webservice_type);
        $this->assertSame('AR', $transaction->country);
        $this->assertSame(11, (int) $transaction->shipment_id);
        $this->assertSame(7, (int) $transaction->voyage_id);
        $this->assertSame('testing', $transaction->environment);
        $this->assertSame(1, WebserviceTransaction::count());
        $this->assertSame(0, WebserviceTrack::count());

        $dom = new \DOMDocument();
        $this->assertTrue(@$dom->loadXML($transaction->request_xml, LIBXML_NONET));
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[local-name()="TitTransEnvio"]')->length);
        $this->assertSame(2, $xpath->query('//*[local-name()="Envio"]')->length);
        $this->assertSame('40.1000', $xpath->evaluate(
            'string((//*[local-name()="Envio"])[1]//*[local-name()="Item"]/*[local-name()="peso"])'
        ));
        $this->assertSame('60.1500', $xpath->evaluate(
            'string((//*[local-name()="Envio"])[2]//*[local-name()="Item"]/*[local-name()="peso"])'
        ));
    }

    public function test_duplicate_reservation_is_blocked_and_does_not_replace_xml(): void
    {
        $original = $this->reserve();
        try {
            $this->reserve();
            $this->fail('Se aceptó una reserva duplicada.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('ya registra un intento', $exception->getMessage());
        }
        $this->assertSame(1, WebserviceTransaction::count());
        $this->assertSame($original->request_xml, $original->fresh()->request_xml);
    }

    public function test_any_previous_error_or_timeout_blocks_new_reservation(): void
    {
        foreach (['error', 'expired', 'cancelled', 'sent', 'success'] as $status) {
            $existing = $this->reserve();
            $existing->update(['status' => $status]);
            try {
                $this->reserve();
                $this->fail("Estado {$status} permitió volver a reservar.");
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('ya registra un intento', $exception->getMessage());
            }
            $this->assertSame(1, WebserviceTransaction::count());
            $existing->delete();
        }
    }

    public function test_incomplete_customs_and_nonzero_fob_leave_no_reservation(): void
    {
        foreach ([
            [$this->template(), [11 => $this->customs()[11]], 'Faltan datos aduaneros'],
            [str_replace('<montoFob>0.0000</montoFob>',
                '<montoFob>100.0000</montoFob>', $this->template()),
                $this->customs(), 'reparto de importes'],
        ] as [$soap, $customs, $message]) {
            try {
                $this->micDta->reserveHidroviaTitEnviosForReview($this->voyage, 31, $soap, $customs);
                $this->fail('Se reservó una solicitud aduanera incompleta.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
            $this->assertSame(0, WebserviceTransaction::count());
            $this->assertSame(0, WebserviceTrack::count());
        }
    }

    public function test_reservation_cannot_be_misreported_as_accepted_response(): void
    {
        $transaction = $this->reserve();
        $confirmation = app(HidroviaTransshipmentRecordedTrackService::class);
        try {
            $confirmation->confirmRecordedResponse(101, $transaction->id, 31);
            $this->fail('Se confirmó una reserva no transmitida.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('solicitud y respuesta ARCA completas', $exception->getMessage());
        }
        $transaction->update(['status' => 'sent', 'response_xml' => $this->response(),
            'sent_at' => now()->subSecond(), 'response_at' => now()]);
        try {
            $confirmation->confirmRecordedResponse(101, $transaction->id, 31);
            $this->fail('Se confirmó una transacción aún en revisión manual.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('solicitud y respuesta ARCA completas', $exception->getMessage());
        }
        $this->assertSame('sent', $transaction->fresh()->status);
        $this->assertSame(0, WebserviceTrack::count());
    }

    public function test_offline_recorded_response_maps_real_format_and_replays_idempotently(): void
    {
        // Simula EXCLUSIVAMENTE el registro posterior de una respuesta: no
        // constituye autorización ni prueba de un envío aduanero verdadero.
        $transaction = $this->reserve();
        $transaction->update(['status' => 'sent', 'response_xml' => $this->response(),
            'sent_at' => now()->subSecond(), 'response_at' => now(),
            'requires_manual_review' => false]);
        $confirmation = app(HidroviaTransshipmentRecordedTrackService::class);
        $rows = $confirmation->confirmRecordedResponse(101, $transaction->id, 31);
        $this->assertCount(2, $rows);
        $this->assertSame('success', $transaction->fresh()->status);
        $this->assertSame([11, 12], array_map(fn ($r) => (int) $r->shipment_id, $rows));
        $this->assertSame([1, 2], array_map(fn ($r) => $r->afip_metadata['idEnvio'], $rows));
        $this->assertSame(['2026AR0000000011', '2026AR0000000022'],
            array_map(fn ($r) => $r->track_number, $rows));
        $this->assertSame([31, 31], array_map(fn ($r) => (int) $r->bill_of_lading_id, $rows));
        $before = WebserviceTrack::orderBy('id')->get()
            ->map(fn (WebserviceTrack $r) => $r->getRawOriginal())->all();
        $again = $confirmation->confirmRecordedResponse(101, $transaction->id, 31);
        $after = WebserviceTrack::orderBy('id')->get()
            ->map(fn (WebserviceTrack $r) => $r->getRawOriginal())->all();
        $this->assertCount(2, $again);
        $this->assertSame($before, $after);
        $this->assertSame(2, WebserviceTrack::count());
    }

    public function test_arca_error_keeps_response_unconfirmed_and_leaves_no_tracks(): void
    {
        $transaction = $this->reserve();
        $transaction->update(['status' => 'sent', 'response_xml' => $this->response('41933'),
            'sent_at' => now()->subSecond(), 'response_at' => now(),
            'requires_manual_review' => false]);
        try {
            app(HidroviaTransshipmentRecordedTrackService::class)
                ->confirmRecordedResponse(101, $transaction->id, 31);
            $this->fail('Se confirmaron TRACKs ante error ARCA.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('error aduanero', $exception->getMessage());
        }
        $this->assertSame('sent', $transaction->fresh()->status);
        $this->assertSame(0, WebserviceTrack::count());
    }

    public function test_other_company_is_rejected_and_draft_is_frozen_after_reservation(): void
    {
        try {
            $this->micDta->reserveHidroviaTitEnviosForReview(
                Voyage::findOrFail(8), 31, $this->template(), $this->customs()
            );
            $this->fail('Se aceptó un viaje ajeno.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('no autorizados', $exception->getMessage());
        }
        $transaction = $this->reserve();
        $this->assertNotNull($transaction->id);
        try {
            app(\App\Services\Simple\HidroviaTransshipmentDraftService::class)
                ->replaceDraft($this->voyage, 101, BillOfLading::findOrFail(31), [
                    ['shipment_id' => 11, 'shipment_item_id' => 41,
                        'package_quantity' => 10, 'gross_weight_kg' => '100.25'],
                ]);
            $this->fail('Se alteró la distribución después de reservar.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('resultado incierto', $exception->getMessage());
        }
        $this->assertSame(2, HidroviaTransshipmentAllocation::count());
        $this->assertSame(0, WebserviceTrack::count());
    }
}
