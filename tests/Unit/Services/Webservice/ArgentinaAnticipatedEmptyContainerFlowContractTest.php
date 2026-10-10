<?php

namespace Tests\Unit\Services\Webservice;

use App\Jobs\ProcessManifestImportJob;
use App\Models\BillOfLading;
use App\Models\Company;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\Voyage;
use App\Services\Simple\ArgentinaAnticipatedService;
use App\Services\Simple\SimpleXmlGenerator;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use XMLWriter;

class ArgentinaAnticipatedEmptyContainerFlowContractTest extends TestCase
{
    public function test_lastre_with_empty_container_is_blocked_locally(): void
    {
        $voyage = (new Voyage())->forceFill([
            'is_empty_transport' => 'S',
        ]);

        $shipment = new Shipment();
        $bill = new BillOfLading();
        $item = new ShipmentItem();
        $container = (new Container())->forceFill([
            'container_number' => 'BMOU5955186',
            'condition' => 'V',
            'container_condition' => 'V',
        ]);

        $container->setRelation(
            'containerType',
            (new ContainerType())->forceFill(['iso_code' => '45G1'])
        );
        $container->setRelation('operatorClient', null);
        $item->setRelation('containers', collect([$container]));
        $bill->setRelation('loadingPort', null);
        $bill->setRelation('dischargePort', null);
        $bill->setRelation('shipmentItems', collect([$item]));
        $shipment->setRelation('billsOfLading', collect([$bill]));
        $voyage->setRelation('shipments', collect([$shipment]));

        $company = (new Company())->forceFill([
            'ws_environment' => 'testing',
        ]);
        $generator = new SimpleXmlGenerator($company);

        $writer = new XMLWriter();
        $writer->openMemory();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'un transporte en lastre no puede informar ContenedoresVaciosCorreo'
        );

        (new ReflectionMethod($generator, 'addContainersInformation'))
            ->invoke($generator, $writer, $voyage);
    }

    public function test_registrar_viaje_does_not_send_empty_containers_and_titles_keep_them(): void
    {
        $generator = file_get_contents(
            dirname(__DIR__, 4)
            . '/app/Services/Simple/SimpleXmlGenerator.php'
        );
        $service = file_get_contents(
            dirname(__DIR__, 4)
            . '/app/Services/Simple/ArgentinaAnticipatedService.php'
        );

        $this->assertIsString($generator);
        $this->assertIsString($service);

        $registrarViajeStart = strpos(
            $generator,
            'public function createRegistrarViajeXml'
        );
        $rectificarViajeStart = strpos(
            $generator,
            'public function createRectificarViajeXml'
        );
        $registrarTitulosStart = strpos(
            $generator,
            'public function createRegistrarTitulosCbcXml'
        );
        $cerrarViajeStart = strpos(
            $generator,
            'public function generateCerrarViajeXml'
        );

        $this->assertNotFalse($registrarViajeStart);
        $this->assertNotFalse($rectificarViajeStart);
        $this->assertNotFalse($registrarTitulosStart);
        $this->assertNotFalse($cerrarViajeStart);

        $registrarViaje = substr(
            $generator,
            $registrarViajeStart,
            $rectificarViajeStart - $registrarViajeStart
        );
        $registrarTitulos = substr(
            $generator,
            $registrarTitulosStart,
            $cerrarViajeStart - $registrarTitulosStart
        );

        $this->assertStringNotContainsString(
            '$this->addContainersInformation($w, $voyage);',
            $registrarViaje
        );
        $this->assertStringContainsString(
            '$this->addContainersInformation($w, $voyage, \'ar:\');',
            $registrarTitulos
        );
        $this->assertStringContainsString(
            '$this->validateEmptyContainerOrigins($voyage)',
            $service
        );
        $this->assertStringContainsString(
            'ContenedoresVaciosCorreo (ARCA 11326)',
            $service
        );
    }

    public function test_imported_loading_date_is_promoted_only_for_empty_bills(): void
    {
        $job = (new ReflectionClass(ProcessManifestImportJob::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($job, 'billHasEmptyContainer');

        $emptyBill = $this->billWithContainer('V', 'V');
        $loadedBill = $this->billWithContainer('L', 'H');

        $this->assertTrue($method->invoke($job, $emptyBill));
        $this->assertFalse($method->invoke($job, $loadedBill));

        $source = file_get_contents(
            dirname(__DIR__, 4) . '/app/Jobs/ProcessManifestImportJob.php'
        );

        $this->assertIsString($source);

        $guard = strpos(
            $source,
            'if ($this->billHasEmptyContainer($bill))'
        );
        $assignment = strpos(
            $source,
            '$bill->origin_loading_date = $this->loadingDate;'
        );

        $this->assertNotFalse($guard);
        $this->assertNotFalse($assignment);
        $this->assertLessThan($assignment, $guard);
    }

    private function billWithContainer(
        string $condition,
        string $containerCondition
    ): BillOfLading {
        $bill = new BillOfLading();
        $item = new ShipmentItem();
        $container = (new Container())->forceFill([
            'condition' => $condition,
            'container_condition' => $containerCondition,
        ]);

        $item->setRelation('containers', collect([$container]));
        $bill->setRelation('shipmentItems', collect([$item]));

        return $bill;
    }
}
