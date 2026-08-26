<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\BillOfLading;
use App\Models\Client;
use App\Models\PackagingType;
use App\Models\Port;
use App\Models\ShipmentItem;
use App\Services\Webservice\ManeFileGeneratorService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class LoginManeContractTest extends TestCase
{
    private function service(): ManeFileGeneratorService
    {
        /*
         * record2/record3 no dependen de Company.
         * Evitamos montar BD sólo para probar la serialización.
         */
        return (
            new ReflectionClass(
                ManeFileGeneratorService::class
            )
        )->newInstanceWithoutConstructor();
    }

    private function invoke(
        ManeFileGeneratorService $service,
        string $method,
        array $args
    ): mixed {
        $reflection = new ReflectionMethod(
            ManeFileGeneratorService::class,
            $method
        );

        $reflection->setAccessible(true);

        return $reflection->invokeArgs(
            $service,
            $args
        );
    }

    private function loginGraph(): array
    {
        $loadingPort = new Port();
        $loadingPort->forceFill([
            'code' => 'ARBUE',
            'webservice_config' => [
                'customs_office_id' => '001',
            ],
        ]);

        $dischargePort = new Port();
        $dischargePort->forceFill([
            'code' => 'BRNVT',
        ]);

        $shipper = new Client();
        $shipper->forceFill([
            'legal_name' => 'PBBPOLISUR S.R.L.',
            'tax_id' => '30560254195',
        ]);

        $notify = new Client();
        $notify->forceFill([
            'legal_name' =>
                'INDUSTRIA E COMECIO DE EMBALAGENS M',
        ]);

        $item = new ShipmentItem();
        $item->forceFill([
            'line_number' => 1,
            'package_quantity' => 0,
            'gross_weight_kg' => 137700.0,
            'container_condition' => 'H',
            'item_description' =>
                'POLIMEROS DE ETILENO EN FORMAS PRIMARIAS.',
            'package_numbers' => null,
            'consignee_document_type' => null,
            'consignee_tax_id' => null,
            'tariff_position' => null,
            'is_secure_logistics_operator' => 'N',
            'is_monitored_transit' => 'N',
            'is_renar' => 'N',
        ]);

        $bill = new BillOfLading();
        $bill->forceFill([
            'bill_number' => '004N902806403',
            'source_format' => 'LOGIN_XML',
            'cargo_marks' => null,
            'notify_party_text' => null,
            'is_consolidated' => false,
            'is_transit_transshipment' => false,
            'container_count' => 5,

            /*
             * Vacío deliberadamente:
             * loginNcmDescription usa entonces la descripción
             * del ShipmentItem sin depender de catálogo/BD.
             */
            'commodity_code' => null,
            'commodity_codes' => [],
        ]);

        $bill->setRelation(
            'loadingPort',
            $loadingPort
        );

        $bill->setRelation(
            'dischargePort',
            $dischargePort
        );

        $bill->setRelation(
            'shipper',
            $shipper
        );

        $bill->setRelation(
            'notifyParty',
            $notify
        );

        $bill->setRelation(
            'shipmentItems',
            collect([$item])
        );

        return [$bill, $item];
    }

    public function test_login_record2_matches_roberto_contract(): void
    {
        [$bill] = $this->loginGraph();

        $line = $this->invoke(
            $this->service(),
            'record2',
            [$bill]
        );

        $this->assertSame(
            '@2@A@BRNVT@004N902806403@SM@30560254195'
            . '@INDUSTRIA E COMECIO DE EMBALAGENS M'
            . '@N@N@N@@@@@@@N@',
            $line
        );

        $fields = explode(
            '@',
            substr($line, 1, -1)
        );

        $this->assertCount(17, $fields);

        $this->assertSame('N', $fields[7]);   // F08
        $this->assertSame('N', $fields[8]);   // F09
        $this->assertSame('N', $fields[9]);   // F10

        $this->assertSame('', $fields[14]);   // F15
        $this->assertSame('', $fields[15]);   // F16
        $this->assertSame('N', $fields[16]);  // F17
    }

    public function test_login_record3_uses_normalized_container_count(): void
    {
        [$bill, $item] = $this->loginGraph();

        $line = $this->invoke(
            $this->service(),
            'record3',
            [$bill, $item]
        );

        $this->assertSame(
            '@3@A@BRNVT004N902806403@001@05@T@H'
            . '@5@137700.000'
            . '@POLIMEROS DE ETILENO EN FORMAS PRIMARIAS.'
            . '@S/N@@',
            $line
        );

        $fields = explode(
            '@',
            substr($line, 1, -1)
        );

        $this->assertCount(12, $fields);

        $this->assertSame('5', $fields[7]);          // F08
        $this->assertSame('137700.000', $fields[8]); // F09

        /*
         * El arreglo no debe falsear la semántica del modelo.
         * Los bultos siguen siendo desconocidos.
         */
        $this->assertSame(
            0,
            $item->package_quantity
        );
    }

    public function test_non_login_record3_keeps_package_quantity_semantics(): void
    {
        [$bill, $item] = $this->loginGraph();

        $bill->source_format = 'OTHER';
        $item->package_quantity = 77;
        $item->gross_weight_kg = 123.45;
        $item->package_numbers = 'PK1';

        $packaging = new PackagingType();
        $packaging->forceFill([
            'code' => '05',
        ]);

        $item->setRelation(
            'packagingType',
            $packaging
        );

        $line = $this->invoke(
            $this->service(),
            'record3',
            [$bill, $item]
        );

        $fields = explode(
            '@',
            substr($line, 1, -1)
        );

        $this->assertSame(
            '77',
            $fields[7]
        );
    }
}
