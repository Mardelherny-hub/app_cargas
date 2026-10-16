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
        $this->expectExceptionMessage('Operación ATA-DESC desconocida');
        $this->service()->ejecutarExpediente($operation, 'borrar_todo', [44]);
    }

    #[Test]
    public function independent_flow_rejects_empty_selections_before_database_or_network(): void
    {
        $operation = new DeconsolidationOperation();
        $operation->id = 11;
        $operation->company_id = 101;
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Seleccione los conocimientos');
        $this->service()->ejecutarExpediente($operation, 'registrar', []);
    }
}
