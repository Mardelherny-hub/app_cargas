<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Container;
use App\Models\ContainerType;
use App\Models\Client;
use App\Models\Port;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MÓDULO 3: VIAJES Y CARGAS - Container Seeder
 * 
 * Seeder para contenedores físicos usando datos de seeders existentes:
 * - ContainerTypesSeeder: 20GP, 40HC, 20OT
 * - ClientsSeeder: Clientes argentinos y paraguayos
 * - BaseCatalogsSeeder: Puertos ARBUE, ARROS, PYASU, PYCDE
 * 
 * REQUIERE EJECUTAR ANTES:
 * - BaseCatalogsSeeder
 * - ContainerTypesSeeder  
 * - ClientsSeeder
 */
class ContainerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📦 Creando contenedores físicos...');

        // Verificar datos relacionados necesarios
        if (!$this->checkRequiredData()) {
            return;
        }

        // Obtener datos de seeders existentes
        $containerTypes = ContainerType::where('active', true)->get();
        $clients = Client::where('status', 'active')->get();
        // CORREGIDO: Solo puertos de Argentina y Paraguay para evitar timeout
        $countryIds = \App\Models\Country::whereIn('alpha2_code', ['AR', 'PY', 'BO', 'UY', 'BR'])->pluck('id');

        $ports = Port::where('active', true)
            ->whereIn('country_id', $countryIds)
            ->select('id', 'name', 'code', 'city', 'country_id')
            ->orderBy('name')
            ->get();
        if ($containerTypes->isEmpty() || $clients->isEmpty() || $ports->isEmpty()) {
            $this->command->error('❌ Faltan datos base. Ejecute primero ContainerTypesSeeder, ClientsSeeder y BaseCatalogsSeeder.');
            return;
        }

        DB::transaction(function () use ($containerTypes, $clients, $ports) {
            // Crear contenedores usando datos reales
            $this->createContainersFromExistingData($containerTypes, $clients, $ports);
        });

        $this->command->info('✅ Contenedores creados exitosamente');
    }

    /**
     * Verificar que existan los datos relacionados necesarios
     */
    private function checkRequiredData(): bool
    {
        $errors = [];

        if (!ContainerType::exists()) {
            $errors[] = 'ContainerTypes no encontrados. Ejecute ContainerTypesSeeder.';
        }

        if (!Client::exists()) {
            $errors[] = 'Clients no encontrados. Ejecute ClientsSeeder.';
        }

        if (!Port::exists()) {
            $errors[] = 'Ports no encontrados. Ejecute BaseCatalogsSeeder.';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->command->error("❌ {$error}");
            }
            return false;
        }

        return true;
    }

    /**
     * Crear contenedores usando datos de seeders existentes
     */
    private function createContainersFromExistingData($containerTypes, $clients, $ports): void
    {
        $this->command->line('  └── Creando contenedores con números realistas...');

        // Prefijos reales de líneas navieras
        $containerPrefixes = ['MEDU', 'TCLU', 'CRLU', 'CAIU', 'MSCU', 'HLBU', 'CMAU', 'GESU'];
        
        // Condiciones del enum de la migración
        $conditions = ['V', 'D', 'S', 'P', 'L'];

        $containers = [];
        $containerCount = 0;

        foreach ($containerTypes as $containerType) {
            // Crear varios contenedores por tipo
            $containersPerType = match($containerType->code) {
                '20GP' => 15, // Más comunes
                '40HC' => 12,
                '20OT' => 5,  // Menos comunes
                default => 8
            };

            for ($i = 1; $i <= $containersPerType; $i++) {
                $prefix = $containerPrefixes[array_rand($containerPrefixes)];
                $sequence = str_pad($containerCount + 1000000, 6, '0', STR_PAD_LEFT);
                $containerNumber = $prefix . $sequence;
                
                // Calcular dígito verificador simple
                $checkDigit = $this->calculateCheckDigit($containerNumber);
                
                // Seleccionar datos relacionados aleatoriamente
                $vesselOwner = $clients->random();
                $lesseeClient = $clients->random();
                $operatorClient = $clients->random();
                $currentPort = $ports->random();
                $lastPort = $ports->random();
                $condition = $conditions[array_rand($conditions)];

                // Calcular pesos basados en el tipo
                $tareWeight = $this->getTareWeightByType($containerType->code);
                $maxGrossWeight = $this->getMaxGrossWeightByType($containerType->code);
                $currentGrossWeight = $condition === 'L' ? rand($tareWeight + 1000, $maxGrossWeight) : $tareWeight;
                $cargoWeight = $condition === 'L' ? $currentGrossWeight - $tareWeight : 0;

                $containers[] = [
                    'container_number' => $containerNumber,
                    'container_check_digit' => $checkDigit,
                    'full_container_number' => $containerNumber . $checkDigit,
                    'container_type_id' => $containerType->id,
                    'vessel_owner_id' => $vesselOwner->id,
                    'lessee_client_id' => $lesseeClient->id,
                    'operator_client_id' => $operatorClient->id,
                    'current_port_id' => $currentPort->id,
                    'last_port_id' => $lastPort->id,
                    'tare_weight_kg' => $tareWeight,
                    'max_gross_weight_kg' => $maxGrossWeight,
                    'current_gross_weight_kg' => $currentGrossWeight,
                    'cargo_weight_kg' => $cargoWeight,
                    'condition' => $condition,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $containerCount++;
            }

            $this->command->line("    ✓ {$containerType->code}: {$containersPerType} contenedores");
        }

        // Insertar en lotes para eficiencia
        foreach (array_chunk($containers, 50) as $batch) {
            DB::table('containers')->insert($batch);
        }

        $this->command->info("  📊 Total de contenedores creados: {$containerCount}");
        $this->command->line('  📍 Distribuidos en puertos: ' . $ports->pluck('short_name')->join(', '));
        $this->command->line('  🏢 Con propietarios: ' . $clients->take(3)->pluck('business_name')->join(', ') . '...');
    }

    /**
     * Calcular dígito verificador simple para contenedores
     */
    private function calculateCheckDigit($containerNumber): string
    {
        // Algoritmo simplificado de dígito verificador
        $sum = 0;
        $length = strlen($containerNumber);
        
        for ($i = 0; $i < $length; $i++) {
            $char = $containerNumber[$i];
            if (is_numeric($char)) {
                $sum += intval($char) * ($i + 1);
            } else {
                // Convertir letra a número (A=1, B=2, etc.)
                $sum += (ord($char) - ord('A') + 1) * ($i + 1);
            }
        }
        
        return strval($sum % 10);
    }

    /**
     * Obtener peso tara según tipo de contenedor
     */
    private function getTareWeightByType($containerType): int
    {
        return match($containerType) {
            '20GP' => 2300,
            '40HC' => 4000,
            '20OT' => 2500,
            default => 2800
        };
    }

    /**
     * Obtener peso bruto máximo según tipo de contenedor
     */
    private function getMaxGrossWeightByType($containerType): int
    {
        return match($containerType) {
            '20GP' => 24000,
            '40HC' => 30480,
            '20OT' => 24000,
            default => 26000
        };
    }
}