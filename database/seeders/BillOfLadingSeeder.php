<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\BillOfLading;
use App\Models\Shipment;
use App\Models\Client;
use App\Models\Port;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\User;
use Carbon\Carbon;

/**
 * SEEDER: Bills of Landing - Conocimientos de Embarque
 * 
 * Genera datos de prueba realistas para demostrar la funcionalidad 
 * del sistema de manifiestos.
 * 
 * DEPENDENCIAS: Shipments, Clients, Ports, CargoTypes, PackagingTypes
 * DATOS: Basados en análisis de CSV reales Paraná-Argentina
 */
class BillOfLadingSeeder extends Seeder
{
    /**
     * Datos reales extraídos de los CSV para generar contenido realista
     */
    private $realCargoDescriptions = [
        'New autoparts - HS CODE 870899 - Auto Parts and Accessories',
        'Galvanized Wire - HS CODE 721720 - Steel Wire Products', 
        'Licores - NCM 220870 - Alcoholic Beverages',
        'Nutrisol LS Lignosulfonato de Sodio - NCM 3804.00.20.000X',
        '100% Polyester Fabric - NCM 6001 - Textile Products',
        'Chemical Products - Industrial Grade Chemicals',
        'Agricultural Products - Fertilizers and Pesticides',
        'Construction Materials - Building Supplies',
        'Food Products - Processed Foods',
        'Electronic Components - Technology Parts',
    ];

    private $realContainerTypes = [
        '40HC', '20DV', '40DV', '40FR', '20TN', '40RH', '20OT', '20RF'
    ];

    private $realPackageTypes = [
        'PACKAGE', 'ROLLS', 'BOX', 'BAGS', 'DRUMS', 'PALLETS', 'UNITS', 'PIECES', 'CARTONS'
    ];

    private $realFreightTermsData = [
        'FREE IN / FREE OUT', 'LINE IN / FREE OUT'
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🚢 Iniciando seeder de Bills of Landing...');

        // Verificar dependencias existentes
        if (!$this->checkDependencies()) {
            $this->command->error('❌ Faltan dependencias. Ejecutar seeders requeridos primero.');
            return;
        }

        // Limpiar datos existentes
        $this->cleanExistingData();

        // Obtener datos existentes de las tablas relacionadas
        $existingData = $this->getExistingData();

        // Generar Bills of Landing
        $this->generateBillsOfLading($existingData);

        $this->command->info('✅ Seeder de Bills of Landing completado exitosamente.');
    }

    /**
     * Verificar que existan las dependencias necesarias
     */
    private function checkDependencies(): bool
    {
        $dependencies = [
            'shipments' => Shipment::count(),
            'clients' => Client::count(), 
            'ports' => Port::count(),
            'cargo_types' => CargoType::count(),
            'packaging_types' => PackagingType::count(),
            'users' => User::count(),
        ];

        foreach ($dependencies as $table => $count) {
            if ($count === 0) {
                $this->command->error("❌ No hay datos en la tabla: {$table}");
                return false;
            }
            $this->command->info("✅ {$table}: {$count} registros");
        }

        return true;
    }

    /**
     * Limpiar datos existentes
     */
    private function cleanExistingData(): void
    {
        $this->command->info('🧹 Limpiando datos existentes de Bills of Landing...');
        
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        BillOfLading::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Obtener datos existentes de las tablas relacionadas
     */
    private function getExistingData(): array
    {
        $this->command->info('📊 Obteniendo datos existentes...');

        return [
            'shipments' => Shipment::with(['voyage.company'])->get(),
            'clients' => Client::all(),
            'ports' => Port::all(), 
            'cargo_types' => CargoType::all(),
            'packaging_types' => PackagingType::all(),
            'users' => User::first(), // Solo necesitamos un usuario para created_by
        ];
    }

    /**
     * Generar Bills of Landing de prueba
     */
    private function generateBillsOfLading(array $data): void
    {
        $this->command->info('🏗️ Generando Bills of Landing...');

        $billsCreated = 0;
        $targetCount = min(50, $data['shipments']->count() * 3); // Máximo 50 o 3 por shipment

        foreach ($data['shipments']->take(17) as $index => $shipment) { // Máximo 17 shipments
            // Generar entre 1 y 3 bills por shipment
            $billsPerShipment = rand(1, 3);

            for ($i = 1; $i <= $billsPerShipment; $i++) {
                if ($billsCreated >= $targetCount) break 2;

                $bill = $this->createBillOfLading($shipment, $data, $i, $billsCreated + 1);
                
                if ($bill) {
                    $billsCreated++;
                    
                    if ($billsCreated % 10 === 0) {
                        $this->command->info("📦 Creados: {$billsCreated} Bills of Landing");
                    }
                }
            }
        }

        $this->command->info("🎉 Total Bills of Landing creados: {$billsCreated}");
    }

    /**
     * Crear un Bill of Landing individual
     */
    private function createBillOfLading(Shipment $shipment, array $data, int $sequence, int $globalIndex): ?BillOfLading
    {
        try {
            // Seleccionar datos aleatorios de los existentes
            $shipper = $data['clients']->random();
            $consignee = $data['clients']->where('id', '!=', $shipper->id)->random();
            $notifyParty = rand(0, 1) ? $data['clients']->where('id', '!=', $shipper->id)->where('id', '!=', $consignee->id)->random() : null;
            
            $loadingPort = $data['ports']->random();
            $dischargePort = $data['ports']->where('id', '!=', $loadingPort->id)->random();
            
            $cargoType = $data['cargo_types']->random();
            $packagingType = $data['packaging_types']->random();

            // Generar datos realistas
            $billDate = Carbon::now()->subDays(rand(1, 30));
            $loadingDate = $billDate->copy()->addDays(rand(1, 5));
            
            $totalPackages = rand(10, 500);
            $grossWeight = rand(1000, 25000); // kg
            $netWeight = $grossWeight * (rand(80, 95) / 100); // 80-95% del peso bruto
            $volume = rand(10, 60); // m3

            return BillOfLading::create([
                // Relaciones principales
                'shipment_id' => $shipment->id,
                'shipper_id' => $shipper->id,
                'consignee_id' => $consignee->id,
                'notify_party_id' => $notifyParty?->id,
                
                // Puertos
                'loading_port_id' => $loadingPort->id,
                'discharge_port_id' => $dischargePort->id,
                
                // Tipos de carga y embalaje  
                'primary_cargo_type_id' => $cargoType->id,
                'primary_packaging_type_id' => $packagingType->id,
                
                // Identificación del conocimiento
                'bill_number' => 'BL' . str_pad($globalIndex, 6, '0', STR_PAD_LEFT),
                'internal_reference' => 'REF-' . $shipment->id . '-' . $sequence,
                'bill_date' => $billDate,
                'manifest_line_number' => $sequence,
                
                // Fechas operacionales
                'loading_date' => $loadingDate,
                'discharge_date' => $loadingDate->copy()->addDays(rand(3, 10)),
                'cargo_ready_date' => $billDate->copy()->addDays(rand(0, 2)),
                
                // Términos comerciales
                'freight_terms' => $this->getRandomFreightTerms(),
                'payment_terms' => ['cash', 'credit', 'letter_of_credit'][rand(0, 2)],
                'incoterms' => ['FOB', 'CIF', 'CFR', 'EXW'][rand(0, 3)],
                'currency_code' => ['USD', 'ARS', 'PYG'][rand(0, 2)],
                
                // Medidas y pesos
                'total_packages' => $totalPackages,
                'gross_weight_kg' => $grossWeight,
                'net_weight_kg' => $netWeight,
                'volume_m3' => $volume,
                'measurement_unit' => 'KG',
                'container_count' => rand(1, 3),
                
                // Descripción de carga
                'cargo_description' => $this->realCargoDescriptions[array_rand($this->realCargoDescriptions)],
                'cargo_marks' => 'HANDLE WITH CARE - FRAGILE',
                'commodity_code' => $this->generarateNCMCode(),
                
                // Estados y control
                'status' => ['draft', 'verified', 'pending_review'][rand(0, 2)],
                'priority_level' => ['normal', 'normal', 'normal', 'high'][rand(0, 3)], // Más probabilidad de normal
                
                // Características especiales (principalmente false para simplicidad)
                'requires_inspection' => rand(0, 1) === 1,
                'contains_dangerous_goods' => rand(0, 9) === 1, // 10% probabilidad
                'requires_refrigeration' => rand(0, 9) === 1, // 10% probabilidad
                
                // Auditoría
                'created_by_user_id' => $data['users']->id,
                'last_updated_by_user_id' => $data['users']->id,
            ]);

        } catch (\Exception $e) {
            $this->command->error("❌ Error creando Bill of Landing: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener términos de flete aleatorios basados en datos reales
     */
    private function getRandomFreightTerms(): string
    {
        $terms = ['prepaid', 'collect', 'third_party'];
        return $terms[array_rand($terms)];
    }

    /**
     * Generar código NCM/commodity realista
     */
    private function generarateNCMCode(): string
    {
        $codes = [
            '8708.99', '7217.20', '2208.70', '3804.00', '6001.10', 
            '8471.30', '3102.10', '6805.30', '2106.90', '8543.70'
        ];
        
        return $codes[array_rand($codes)] . '.00.00' . chr(rand(65, 90));
    }
}