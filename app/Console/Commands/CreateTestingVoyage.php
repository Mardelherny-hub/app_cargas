<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Voyage;
use App\Models\Vessel;
use App\Models\Port;
use App\Models\Country;
use App\Models\Shipment;
use App\Models\BillOfLading;
use App\Models\Container;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Comando para crear un voyage de testing simple y completo para AFIP
 * 
 * USO:
 * php artisan create:testing-voyage
 * php artisan create:testing-voyage --company=1005
 * php artisan create:testing-voyage --route=ARBUE-ARROS
 */
class CreateTestingVoyage extends Command
{
    protected $signature = 'create:testing-voyage 
                           {--company= : ID de empresa específica}
                           {--route=ARBUE-ARROS : Ruta del voyage (origen-destino)}
                           {--clean : Limpiar voyages de testing previos}';

    protected $description = 'Crear voyage de testing simple y completo para validar AFIP';

    public function handle()
    {
        $this->info('🚀 CREANDO VOYAGE DE TESTING AFIP');
        $this->info('=================================');

        try {
            // 1. Limpiar si se solicita
            if ($this->option('clean')) {
                $this->cleanTestingVoyages();
            }

            // 2. Obtener empresa
            $company = $this->getTestingCompany();
            if (!$company) {
                return Command::FAILURE;
            }

            // 3. Crear voyage completo
            $voyage = $this->createCompleteVoyage($company);
            if (!$voyage) {
                return Command::FAILURE;
            }

            // 4. Mostrar resumen
            $this->showVoyageSummary($voyage);

            $this->info('✅ Voyage de testing creado exitosamente');
            $this->info("🔗 ID del voyage: {$voyage->id}");
            $this->info("🎯 Voyage number: {$voyage->voyage_number}");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error creando voyage de testing: ' . $e->getMessage());
            if ($this->option('detailed')) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Obtener empresa para testing
     */
    private function getTestingCompany(): ?Company
    {
        $companyId = $this->option('company');
        
        if ($companyId) {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("❌ Empresa con ID {$companyId} no encontrada");
                return null;
            }
        } else {
            // Buscar empresa MAERSK por defecto
            $company = Company::where('legal_name', 'LIKE', '%MAERSK%')->first();
            if (!$company) {
                $this->error('❌ No se encontró empresa MAERSK para testing');
                return null;
            }
        }

        $this->info("✅ Empresa: {$company->legal_name} (ID: {$company->id})");
        return $company;
    }

    /**
     * Crear voyage completo con datos mínimos pero válidos
     */
    private function createCompleteVoyage(Company $company): ?Voyage
    {
        // 1. Obtener puertos
        $ports = $this->getTestingPorts();
        if (!$ports) {
            return null;
        }

        // 2. Obtener vessel
        $vessel = $this->getTestingVessel($company);
        if (!$vessel) {
            return null;
        }

        // 3. Crear voyage
        $voyage = $this->createVoyageRecord($company, $vessel, $ports);
        if (!$voyage) {
            return null;
        }

        // 4. Crear shipment
        $shipment = $this->createShipment($voyage);
        if (!$shipment) {
            return null;
        }

        // 5. Crear Bill of Lading
        $bill = $this->createBillOfLading($shipment);
        if (!$bill) {
            return null;
        }

        // 6. Crear Container
        $container = $this->createContainer($shipment);
        if (!$container) {
            return null;
        }

        return $voyage;
    }

    /**
     * Obtener puertos para testing
     */
    private function getTestingPorts(): ?array
    {
        $route = $this->option('route');
        [$originCode, $destCode] = explode('-', $route);

        $origin = Port::where('code', $originCode)->first();
        $destination = Port::where('code', $destCode)->first();

        if (!$origin || !$destination) {
            $this->error("❌ Puertos no encontrados: {$originCode} o {$destCode}");
            $this->info("💡 Puertos disponibles: " . Port::pluck('code')->take(10)->join(', '));
            return null;
        }

        $this->info("✅ Ruta: {$origin->name} → {$destination->name}");
        return ['origin' => $origin, 'destination' => $destination];
    }

    /**
     * Obtener vessel para testing
     */
    private function getTestingVessel(Company $company): ?Vessel
    {
        $vessel = Vessel::where('company_id', $company->id)
                       ->where('active', true)
                       ->first();

        if (!$vessel) {
            $this->error('❌ No se encontró vessel activo para la empresa');
            return null;
        }

        $this->info("✅ Vessel: {$vessel->name}");
        return $vessel;
    }

    /**
     * Crear registro de voyage
     */
    private function createVoyageRecord(Company $company, Vessel $vessel, array $ports): Voyage
    {
        $voyageNumber = 'TEST-' . date('Ymd') . '-' . sprintf('%03d', rand(1, 999));

        return Voyage::create([
            'voyage_number' => $voyageNumber,
            'company_id' => $company->id,
            'lead_vessel_id' => $vessel->id,
            'origin_port_id' => $ports['origin']->id,
            'destination_port_id' => $ports['destination']->id,
            'origin_country_id' => $ports['origin']->country_id,
            'destination_country_id' => $ports['destination']->country_id,
            'departure_date' => Carbon::now()->addDays(1),
            'estimated_arrival_date' => Carbon::now()->addDays(3),
            'status' => 'approved',
            'voyage_type' => 'commercial',
            'cargo_type' => 'containerized',
            'is_consolidated' => true,
            'total_containers' => 1,
            'total_cargo_weight' => 25000, // 25 toneladas
            'total_bills_of_lading' => 1,
        ]);
    }

    /**
     * Crear shipment
     */
    private function createShipment(Voyage $voyage): Shipment
    {
        $shipmentNumber = 'SHP-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));

        return Shipment::create([
            'shipment_number' => $shipmentNumber,
            'voyage_id' => $voyage->id,
            'company_id' => $voyage->company_id,
            'origin_port_id' => $voyage->origin_port_id,
            'destination_port_id' => $voyage->destination_port_id,
            'status' => 'confirmed',
            'cargo_type' => 'containerized',
            'total_weight' => 25000,
            'total_volume' => 33.2, // m³ para contenedor 20'
            'currency' => 'USD',
            'freight_terms' => 'CIF',
        ]);
    }

    /**
     * Crear Bill of Lading simple
     */
    private function createBillOfLading(Shipment $shipment): BillOfLading
    {
        $billNumber = 'BL-' . date('Ymd') . '-' . sprintf('%06d', rand(1, 999999));

        return BillOfLading::create([
            'bill_number' => $billNumber,
            'shipment_id' => $shipment->id,
            'shipper_name' => 'EXPORTADORA ARGENTINA S.A.',
            'shipper_address' => 'Av. Corrientes 1234, Buenos Aires, Argentina',
            'consignee_name' => 'IMPORTADORA ROSARIO S.R.L.',
            'consignee_address' => 'San Martin 567, Rosario, Argentina',
            'cargo_description' => 'PRODUCTOS MANUFACTURADOS VARIOS',
            'package_count' => 1,
            'package_type' => 'CONTENEDOR',
            'gross_weight' => 25000,
            'net_weight' => 22000,
            'volume' => 33.2,
            'freight_amount' => 2500.00,
            'currency' => 'USD',
            'freight_terms' => 'CIF',
            'is_master_bill' => true,
        ]);
    }

    /**
     * Crear Container simple
     */
    private function createContainer(Shipment $shipment): Container
    {
        $containerNumber = 'TCLU' . sprintf('%07d', rand(1000000, 9999999));

        return Container::create([
            'container_number' => $containerNumber,
            'full_container_number' => $containerNumber . '2', // Con dígito de verificación
            'condition' => 'good',
            'operational_status' => 'loaded',
            'tare_weight_kg' => 3000,
            'max_gross_weight_kg' => 28000,
            'current_gross_weight_kg' => 25000,
            'cargo_weight_kg' => 22000,
            'customs_seal' => 'SEAL' . sprintf('%06d', rand(100000, 999999)),
            'active' => true,
            'blocked' => false,
            'created_date' => now(),
        ]);
    }

    /**
     * Limpiar voyages de testing previos
     */
    private function cleanTestingVoyages(): void
    {
        $this->info('🧹 Limpiando voyages de testing previos...');
        
        $testVoyages = Voyage::where('voyage_number', 'LIKE', 'TEST-%')->get();
        
        foreach ($testVoyages as $voyage) {
            // Eliminar en orden correcto para evitar constraints
            $voyage->shipments()->each(function($shipment) {
                $shipment->containers()->delete();
                $shipment->billsOfLading()->delete();
                $shipment->delete();
            });
            $voyage->delete();
        }
        
        $this->info("✅ {$testVoyages->count()} voyages de testing eliminados");
    }

    /**
     * Mostrar resumen del voyage creado
     */
    private function showVoyageSummary(Voyage $voyage): void
    {
        $this->info("\n📋 RESUMEN DEL VOYAGE CREADO:");
        $this->info("============================");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $voyage->id],
                ['Número', $voyage->voyage_number],
                ['Empresa', $voyage->company->legal_name],
                ['Vessel', $voyage->vessel->name],
                ['Ruta', $voyage->originPort->code . ' → ' . $voyage->destinationPort->code],
                ['Estado', $voyage->status],
                ['Shipments', $voyage->shipments()->count()],
                ['Bills', $voyage->shipments()->with('billsOfLading')->get()->sum(function($s) { return $s->billsOfLading->count(); })],
                ['Containers', $voyage->shipments()->with('containers')->get()->sum(function($s) { return $s->containers->count(); })],
            ]
        );
    }
}