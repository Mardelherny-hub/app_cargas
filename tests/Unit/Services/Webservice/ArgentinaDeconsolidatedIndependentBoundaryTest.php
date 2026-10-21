<?php

namespace Tests\Unit\Services\Webservice;

use App\Models\Company;
use App\Models\DeconsolidationOperation;
use App\Models\User;
use App\Services\Simple\ArgentinaDeconsolidatedService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Seguridad previa a cualquier acceso a BD, WSAA o SOAP. */
class ArgentinaDeconsolidatedIndependentBoundaryTest extends TestCase
{
    private function service(): ArgentinaDeconsolidatedService
    {
        $company = new Company();
        $company->id = 101;
        $company->ws_environment = 'testing';
        $user = new User();
        $user->id = 205;
        return new ArgentinaDeconsolidatedService($company, $user);
    }

    #[Test]
    public function independent_flow_rejects_foreign_company_before_database_or_network(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->id = 11;
        $operation->company_id = 999;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Expediente de otra empresa');
        $this->service()->ejecutarExpediente($operation, 'registrar', [44]);
    }

    #[Test]
    public function independent_flow_rejects_unknown_methods_before_database_or_network(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->id = 11;
        $operation->company_id = 101;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Seleccioná una operación válida para Desconsolidados');
        $this->service()->ejecutarExpediente($operation, 'borrar_todo', [44]);
    }

    #[Test]
    public function independent_flow_rejects_a_bill_outside_its_operation_using_only_memory_database(): void
    {
        $previous = config('database.default');
        config(['database.connections.ata_desc_isolated_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ], 'database.default' => 'ata_desc_isolated_test']);
        try {
            $schema = \Illuminate\Support\Facades\Schema::connection('ata_desc_isolated_test');
            $schema->create('bills_of_lading', function ($table) {
                $table->increments('id');
                $table->unsignedBigInteger('deconsolidation_operation_id')->nullable();
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->softDeletes();
            });
            \Illuminate\Support\Facades\DB::connection('ata_desc_isolated_test')
                ->table('bills_of_lading')->insert([
                    'id' => 44, 'deconsolidation_operation_id' => 888, 'shipment_id' => null,
                ]);
            $operation = new DeconsolidationOperation();
            $operation->id = 11;
            $operation->company_id = 101;
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('no pertenecen a este expediente');
            $this->service()->ejecutarExpediente($operation, 'registrar', [44]);
        } finally {
            \Illuminate\Support\Facades\DB::purge('ata_desc_isolated_test');
            config(['database.default' => $previous]);
        }
    }

    #[Test]
    public function independent_flow_rejects_empty_selections_before_database_or_network(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->id = 11;
        $operation->company_id = 101;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Seleccioná los conocimientos del expediente');
        $this->service()->ejecutarExpediente($operation, 'registrar', []);
    }
}
