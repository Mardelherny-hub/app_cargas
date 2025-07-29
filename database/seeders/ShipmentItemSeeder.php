<?php

namespace Database\Seeders;

use App\Models\ShipmentItem;
use App\Models\Shipment;
use App\Models\CargoType;
use App\Models\PackagingType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ShipmentItemSeeder - MÓDULO 3: VIAJES Y CARGAS
 * 
 * 100% COHERENTE con migración create_shipments_items_table.php CORREGIDA
 * USA ÚNICAMENTE campos que existen en migración y modelo corregidos
 * JERARQUÍA CORREGIDA: Voyages → Shipments → ShipmentItems (usa shipment_id)
 * 
 * DATOS REALES DEL PARANA.csv:
 * - Descriptions: "New autoparts HS CODE 870899", "GALVANIZED WIRE 721720"
 * - NCM Codes: 8708.99, 7217.2, 2208.7, 3804.00.20, 6001, 2832.30.90
 * - Pack Types: PACKAGE, ROLLS, BOX, BAGS, PALLETS, DRUMS, JUMBO BAGS
 * - Packages: 1-7288 unidades (rangos reales del CSV)
 * - Weights: 1850-78300 kg (datos reales del manifiesto)
 * - Containers: MRSU5847890, MSKU3231020, BCMU7294895, etc.
 */
class ShipmentItemSeeder extends Seeder
{
    /**
     * Datos reales extraídos de PARANA.csv
     */
    private const REAL_CARGO_DATA = [
        [
            'description' => 'New autoparts - Inv: 2274488, 2274486 - HS CODE 870899 - MRN 25SE0000IUZOHBHJA6',
            'ncm_code' => '8708.99',
            'pack_type' => 'PACKAGE',
            'packages' => 28,
            'gross_weight' => 4988,
            'volume' => 57.043,
            'cargo_type' => 'manufactured_goods',
            'packaging_type' => 'packages',
            'brand' => 'OEM PARTS',
            'country_origin' => 'CHN',
        ],
        [
            'description' => 'GALVANIZED WIRE 721720 - HS CODE 721720 - Total 1221 Rolls',
            'ncm_code' => '7217.20',
            'pack_type' => 'ROLLS',
            'packages' => 644,
            'gross_weight' => 28000,
            'volume' => 28.0,
            'cargo_type' => 'steel_products',
            'packaging_type' => 'rolls',
            'brand' => 'STEEL WIRE CORP',
            'country_origin' => 'BRA',
        ],
        [
            'description' => 'LICORES - NCM 220870 - Alcoholic Beverages - Premium Quality Spirits',
            'ncm_code' => '2208.70',
            'pack_type' => 'BOX',
            'packages' => 1470,
            'gross_weight' => 22854,
            'volume' => 44.1,
            'cargo_type' => 'beverages',
            'packaging_type' => 'boxes',
            'brand' => 'PREMIUM SPIRITS',
            'country_origin' => 'ARG',
        ],
        [
            'description' => 'NUTRISOL LS - LIGNOSULFONATO DE SODIO - NCM: 3804.00.20 - Industrial Chemical',
            'ncm_code' => '3804.00.20',
            'pack_type' => 'BAGS',
            'packages' => 84,
            'gross_weight' => 2100,
            'volume' => 8.5,
            'cargo_type' => 'chemicals',
            'packaging_type' => 'bags',
            'brand' => 'NUTRISOL',
            'country_origin' => 'USA',
        ],
        [
            'description' => '100% POLYESTER FABRIC - NCM:6001 - Textile Materials - Industrial Grade',
            'ncm_code' => '6001.00',
            'pack_type' => 'PACKAGES',
            'packages' => 60,
            'gross_weight' => 1850,
            'volume' => 12.5,
            'cargo_type' => 'textiles',
            'packaging_type' => 'packages',
            'brand' => 'TEXTILE CORP',
            'country_origin' => 'IND',
        ],
        [
            'description' => 'UNIZEB GOLD (MZB) - Herbicide - 6080 Bags packed in 152 Pallets - Agricultural Chemical',
            'ncm_code' => '3808.92.93',
            'pack_type' => 'BAGS',
            'packages' => 6080,
            'gross_weight' => 24320,
            'volume' => 185.5,
            'cargo_type' => 'chemicals',
            'packaging_type' => 'bags',
            'brand' => 'AGRO SOLUTIONS',
            'country_origin' => 'CHN',
        ],
        [
            'description' => 'POTASSIUM THIOSULFATE - NCM: 2832.30.90 - Net Weight: 78300 KGS - Fertilizer Grade',
            'ncm_code' => '2832.30.90',
            'pack_type' => 'DRUMS',
            'packages' => 54,
            'gross_weight' => 78300,
            'volume' => 95.2,
            'cargo_type' => 'chemicals',
            'packaging_type' => 'drums',
            'brand' => 'CHEM SOLUTIONS',
            'country_origin' => 'DEU',
        ],
        [
            'description' => 'Calcium Carbonate Uncoated (ACMA 10) - HS CODE: 28365000 - Not Intended for Medicinal Use',
            'ncm_code' => '2836.50.00',
            'pack_type' => 'JUMBO BAGS',
            'packages' => 25,
            'gross_weight' => 25000,
            'volume' => 32.8,
            'cargo_type' => 'chemicals',
            'packaging_type' => 'big_bags',
            'brand' => 'MINERAL TECH',
            'country_origin' => 'ESP',
        ],
        [
            'description' => 'GLUFOSINATE-AMMONIUM 96% TC - NCM: 2931.49.15 - Herbicide Technical Concentrate',
            'ncm_code' => '2931.49.15',
            'pack_type' => 'BAGS',
            'packages' => 108,
            'gross_weight' => 2160,
            'volume' => 6.8,
            'cargo_type' => 'chemicals',
            'packaging_type' => 'bags',
            'brand' => 'AGROCHEM PLUS',
            'country_origin' => 'JPN',
        ],
        [
            'description' => 'HYDRAULIC CYLINDER - HS:8412.21.10 - 12 Pallets = 125 PCS - Industrial Machinery Parts',
            'ncm_code' => '8412.21.10',
            'pack_type' => 'PALLETS',
            'packages' => 12,
            'gross_weight' => 8500,
            'volume' => 24.6,
            'cargo_type' => 'machinery',
            'packaging_type' => 'pallets',
            'brand' => 'HYDRAULICS PRO',
            'country_origin' => 'ITA',
        ],
        [
            'description' => 'REFRACTORY DRY VIBRATING COMPOUND - Industrial Materials - High Temperature Resistant',
            'ncm_code' => '3816.00.00',
            'pack_type' => 'BAGS',
            'packages' => 250,
            'gross_weight' => 12500,
            'volume' => 35.7,
            'cargo_type' => 'construction_materials',
            'packaging_type' => 'bags',
            'brand' => 'REFRACTARIOS SA',
            'country_origin' => 'BRA',
        ],
        [
            'description' => 'POLIMEROS DE POLIPROPILENO - Plastic Raw Materials - Industrial Grade Polymer',
            'ncm_code' => '3901.20.00',
            'pack_type' => 'BOXES',
            'packages' => 180,
            'gross_weight' => 9000,
            'volume' => 42.3,
            'cargo_type' => 'plastics',
            'packaging_type' => 'boxes',
            'brand' => 'POLYMER TECH',
            'country_origin' => 'KOR',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Creando shipment items con datos reales PARANA.csv...');
        $this->command->info('🔗 JERARQUÍA CORREGIDA: Voyages → Shipments → ShipmentItems');

        // Verificar dependencias
        if (!$this->verifyDependencies()) {
            return;
        }

        // Limpiar tabla existente
        DB::table('shipment_items')->delete();

        // Obtener datos necesarios
        $shipments = Shipment::with(['voyage', 'vessel'])->get();
        $cargoTypes = CargoType::all()->keyBy('code');
        $packagingTypes = PackagingType::all()->keyBy('code');
        $adminUser = User::where('email', 'admin@cargas.com')->first();

        if ($shipments->isEmpty()) {
            $this->command->error('❌ No se encontraron shipments. Ejecutar ShipmentSeeder primero.');
            return;
        }

        $itemsCreated = 0;

        foreach ($shipments as $shipment) {
            $itemsForShipment = $this->createItemsForShipment(
                $shipment, 
                $cargoTypes, 
                $packagingTypes, 
                $adminUser
            );
            $itemsCreated += $itemsForShipment;
        }

        $this->command->info("✅ {$itemsCreated} shipment items creados exitosamente");
        $this->showSummary();
    }

    /**
     * Verificar dependencias necesarias
     */
    private function verifyDependencies(): bool
    {
        $dependencies = [
            ['model' => Shipment::class, 'name' => 'Shipments'],
            ['model' => CargoType::class, 'name' => 'Tipos de carga'],
            ['model' => PackagingType::class, 'name' => 'Tipos de embalaje'],
        ];

        foreach ($dependencies as $dep) {
            if (!$dep['model']::exists()) {
                $this->command->error("❌ No se encontraron {$dep['name']}. Ejecutar seeder correspondiente primero.");
                return false;
            }
        }

        return true;
    }

    /**
     * Crear items para un shipment específico
     */
    private function createItemsForShipment(
        Shipment $shipment, 
        $cargoTypes, 
        $packagingTypes, 
        $adminUser
    ): int {
        // Determinar cantidad de items (8-12 como solicitado)
        $itemCount = rand(8, 12);
        $itemsCreated = 0;

        $this->command->line("   📦 Creando {$itemCount} items para {$shipment->shipment_number}");

        for ($i = 1; $i <= $itemCount; $i++) {
            // Seleccionar datos reales del PARANA.csv
            $cargoData = self::REAL_CARGO_DATA[array_rand(self::REAL_CARGO_DATA)];
            
            // Obtener tipos de carga y embalaje
            $cargoType = $cargoTypes->get($cargoData['cargo_type']) ?? $cargoTypes->first();
            $packagingType = $packagingTypes->get($cargoData['packaging_type']) ?? $packagingTypes->first();
            
            if (!$cargoType || !$packagingType) {
                $this->command->warn("⚠️  Falta CargoType o PackagingType para shipment {$shipment->id}, línea {$i}");
                continue;
            }

            // Crear el shipment item
            $itemData = $this->buildShipmentItemData(
                $shipment, 
                $cargoData, 
                $cargoType, 
                $packagingType, 
                $i, 
                $adminUser
            );

            try {
                ShipmentItem::create($itemData);
                $itemsCreated++;
            } catch (\Exception $e) {
                $this->command->error("❌ Error creando item línea {$i}: " . $e->getMessage());
            }
        }

        return $itemsCreated;
    }

    /**
     * Construir datos del shipment item
     * USA ÚNICAMENTE campos que existen en modelo corregido
     */
    private function buildShipmentItemData(
        Shipment $shipment,
        array $cargoData,
        $cargoType,
        $packagingType,
        int $lineNumber,
        $adminUser
    ): array {
        // Ajustar cantidades según el shipment
        $adjustmentFactor = $this->getAdjustmentFactor($shipment);
        $adjustedPackages = max(1, round($cargoData['packages'] * $adjustmentFactor));
        $adjustedWeight = $cargoData['gross_weight'] * $adjustmentFactor;
        $adjustedVolume = $cargoData['volume'] * $adjustmentFactor;

        return [
            // ✅ JERARQUÍA CORREGIDA: usa shipment_id
            'shipment_id' => $shipment->id,
            'cargo_type_id' => $cargoType->id,
            'packaging_type_id' => $packagingType->id,

            // Identificación del ítem
            'line_number' => $lineNumber,
            'item_reference' => $this->generateItemReference($shipment, $lineNumber),
            'lot_number' => $this->generateLotNumber(),
            'serial_number' => $this->generateSerialNumber($cargoData),

            // Cantidades y medidas
            'package_quantity' => $adjustedPackages,
            'gross_weight_kg' => round($adjustedWeight, 2),
            'net_weight_kg' => round($adjustedWeight * 0.85, 2), // 85% del peso bruto
            'volume_m3' => round($adjustedVolume, 3),
            'declared_value' => $this->calculateDeclaredValue($adjustedWeight, $cargoData),
            'currency_code' => 'USD',

            // Descripciones
            'item_description' => $cargoData['description'],
            'cargo_marks' => $this->generateCargoMarks($shipment, $lineNumber),
            'commodity_code' => $cargoData['ncm_code'],
            'commodity_description' => $this->extractCommodityDescription($cargoData['description']),

            // Información comercial
            'brand' => $cargoData['brand'],
            'model' => $this->generateModel($cargoData),
            'manufacturer' => $this->generateManufacturer($cargoData),
            'country_of_origin' => $cargoData['country_origin'],

            // Detalles del embalaje
            'package_type_description' => $cargoData['pack_type'],
            'package_dimensions' => json_encode($this->generatePackageDimensions($cargoData)),
            'units_per_package' => $this->calculateUnitsPerPackage($cargoData),
            'unit_of_measure' => $this->getUnitOfMeasure($cargoData),

            // Características especiales
            'is_dangerous_goods' => $this->isDangerousGoods($cargoData),
            'un_number' => $this->getUNNumber($cargoData),
            'imdg_class' => $this->getIMDGClass($cargoData),
            'is_perishable' => $this->isPerishable($cargoData),
            'is_fragile' => $this->isFragile($cargoData),
            'requires_refrigeration' => $this->requiresRefrigeration($cargoData),
            'temperature_min' => $this->getTemperatureMin($cargoData),
            'temperature_max' => $this->getTemperatureMax($cargoData),

            // Regulatorio
            'requires_permit' => $this->requiresPermit($cargoData),
            'permit_number' => $this->getPermitNumber($cargoData),
            'requires_inspection' => $this->requiresInspection($cargoData),
            'inspection_type' => $this->getInspectionType($cargoData),

            // Integración webservice
            'webservice_item_id' => $this->generateWebserviceItemId($shipment, $lineNumber),
            'packaging_code' => $packagingType->code,
            'webservice_data' => json_encode($this->generateWebserviceData($cargoData)),

            // Estado
            'status' => $this->getItemStatus($shipment),
            'has_discrepancies' => rand(0, 20) === 0, // 5% tiene discrepancias
            'discrepancy_notes' => null,
            'requires_review' => rand(0, 10) === 0, // 10% requiere revisión

            // Auditoría
            'created_date' => now()->subDays(rand(0, 7)),
            'created_by_user_id' => $adminUser?->id,
            'last_updated_date' => now(),
            'last_updated_by_user_id' => $adminUser?->id,
        ];
    }

    /**
     * Obtener factor de ajuste según el shipment
     */
    private function getAdjustmentFactor(Shipment $shipment): float
    {
        // Ajustar según la utilización del shipment
        $utilization = max(0.1, min(1.0, $shipment->utilization_percentage / 100));
        return $utilization * rand(80, 120) / 100;
    }

    /**
     * Generar referencia del ítem
     */
    private function generateItemReference(Shipment $shipment, int $lineNumber): string
    {
        return $shipment->shipment_number . '-L' . str_pad($lineNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generar número de lote
     */
    private function generateLotNumber(): string
    {
        return 'LOT' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generar número de serie (solo para ciertos tipos)
     */
    private function generateSerialNumber(array $cargoData): ?string
    {
        $needsSerial = in_array($cargoData['cargo_type'], ['machinery', 'manufactured_goods']);
        
        if ($needsSerial && rand(0, 1)) {
            return 'SN' . date('y') . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        }
        
        return null;
    }

    /**
     * Calcular valor declarado según tipo de mercadería
     */
    private function calculateDeclaredValue(float $weight, array $cargoData): float
    {
        $valuePerKg = match($cargoData['cargo_type']) {
            'machinery' => rand(15, 45),
            'manufactured_goods' => rand(8, 25),
            'chemicals' => rand(2, 12),
            'beverages' => rand(5, 20),
            'textiles' => rand(3, 15),
            'plastics' => rand(1, 8),
            'steel_products' => rand(1, 5),
            'construction_materials' => rand(1, 3),
            default => rand(1, 10)
        };

        return round($weight * $valuePerKg, 2);
    }

    /**
     * Generar marcas de la mercadería
     */
    private function generateCargoMarks(Shipment $shipment, int $lineNumber): string
    {
        $marks = [
            "SHIPMENT: {$shipment->shipment_number}",
            "LINE: {$lineNumber}",
            "HANDLE WITH CARE",
            "KEEP DRY",
            "THIS SIDE UP",
            "FRAGILE",
        ];

        return implode(' | ', array_slice($marks, 0, rand(3, 5)));
    }

    /**
     * Extraer descripción del commodity (primeras 100 caracteres)
     */
    private function extractCommodityDescription(string $description): string
    {
        $lines = explode("\n", $description);
        $firstLine = trim($lines[0]);
        
        return substr($firstLine, 0, 100);
    }

    /**
     * Generar modelo (solo para maquinaria y manufacturas)
     */
    private function generateModel(array $cargoData): ?string
    {
        $needsModel = in_array($cargoData['cargo_type'], ['machinery', 'manufactured_goods']);
        
        if ($needsModel) {
            return 'MODEL-' . rand(2020, 2025) . '-' . chr(rand(65, 90)) . rand(100, 999);
        }
        
        return null;
    }

    /**
     * Generar fabricante según categoría
     */
    private function generateManufacturer(array $cargoData): string
    {
        $manufacturers = [
            'manufactured_goods' => ['OEM Industries', 'Global Manufacturing Corp', 'Precision Parts Ltd'],
            'machinery' => ['Heavy Equipment SA', 'Industrial Solutions Inc', 'Machinery Pro'],
            'chemicals' => ['Chemical Corp International', 'AgriChem Solutions', 'Industrial Chemicals Ltd'],
            'beverages' => ['Premium Distilleries', 'Beverage International', 'Spirits & More SA'],
            'textiles' => ['Textile Manufacturing Corp', 'Global Fabrics Ltd', 'Fashion Materials Inc'],
            'steel_products' => ['Steel Works International', 'Metal Industries Corp', 'Iron & Steel SA'],
            'plastics' => ['Polymer Solutions Ltd', 'Plastic Industries Corp', 'Advanced Materials SA'],
            'construction_materials' => ['Construction Materials Corp', 'Building Supplies SA', 'Industrial Materials Ltd'],
            'default' => ['International Trading Co', 'Global Supply Corp', 'Industrial Partners SA'],
        ];

        $categoryManufacturers = $manufacturers[$cargoData['cargo_type']] ?? $manufacturers['default'];
        return $categoryManufacturers[array_rand($categoryManufacturers)];
    }

    /**
     * Generar dimensiones realistas según tipo de embalaje
     */
    private function generatePackageDimensions(array $cargoData): array
    {
        return match($cargoData['pack_type']) {
            'PACKAGE' => ['length' => rand(40, 120), 'width' => rand(30, 80), 'height' => rand(20, 60)],
            'BOX', 'BOXES' => ['length' => rand(30, 60), 'width' => rand(20, 40), 'height' => rand(15, 30)],
            'BAGS' => ['length' => rand(80, 120), 'width' => rand(50, 80), 'height' => rand(15, 25)],
            'DRUMS' => ['diameter' => rand(40, 60), 'height' => rand(80, 120)],
            'PALLETS' => ['length' => 120, 'width' => 100, 'height' => rand(150, 200)],
            'ROLLS' => ['diameter' => rand(100, 150), 'width' => rand(100, 200)],
            'JUMBO BAGS' => ['length' => rand(100, 150), 'width' => rand(100, 150), 'height' => rand(120, 180)],
            default => ['length' => rand(50, 100), 'width' => rand(40, 80), 'height' => rand(30, 60)]
        };
    }

    /**
     * Calcular unidades por bulto según tipo
     */
    private function calculateUnitsPerPackage(array $cargoData): int
    {
        return match($cargoData['pack_type']) {
            'PACKAGE', 'PACKAGES' => rand(1, 50),
            'BOX', 'BOXES' => rand(12, 100),
            'BAGS' => 1,
            'DRUMS' => 1,
            'PALLETS' => rand(50, 500),
            'ROLLS' => 1,
            'JUMBO BAGS' => 1,
            default => rand(1, 100)
        };
    }

    /**
     * Obtener unidad de medida según tipo de carga
     */
    private function getUnitOfMeasure(array $cargoData): string
    {
        return match($cargoData['cargo_type']) {
            'machinery' => 'PCS',
            'chemicals' => 'KG',
            'beverages' => 'LT',
            'textiles' => 'MT',
            'steel_products' => 'KG',
            'plastics' => 'KG',
            'construction_materials' => 'KG',
            default => 'PCS'
        };
    }

    /**
     * Verificar si es mercancía peligrosa
     */
    private function isDangerousGoods(array $cargoData): bool
    {
        $dangerousTypes = ['chemicals'];
        $isDangerousType = in_array($cargoData['cargo_type'], $dangerousTypes);
        
        // NCM codes específicos de mercancías peligrosas
        $dangerousNCM = ['3808.92.93', '2931.49.15', '2832.30.90'];
        $isDangerousNCM = in_array($cargoData['ncm_code'], $dangerousNCM);
        
        return $isDangerousType || $isDangerousNCM;
    }

    /**
     * Obtener número UN para mercancías peligrosas
     */
    private function getUNNumber(array $cargoData): ?string
    {
        if ($this->isDangerousGoods($cargoData)) {
            return 'UN' . rand(1000, 3500);
        }
        return null;
    }

    /**
     * Obtener clase IMDG para mercancías peligrosas
     */
    private function getIMDGClass(array $cargoData): ?string
    {
        if ($this->isDangerousGoods($cargoData)) {
            $classes = ['3', '6.1', '8', '9'];
            return $classes[array_rand($classes)];
        }
        return null;
    }

    /**
     * Verificar si es perecedero
     */
    private function isPerishable(array $cargoData): bool
    {
        return $cargoData['cargo_type'] === 'beverages' && rand(0, 2) === 0;
    }

    /**
     * Verificar si es frágil
     */
    private function isFragile(array $cargoData): bool
    {
        $fragileTypes = ['beverages', 'manufactured_goods'];
        return in_array($cargoData['cargo_type'], $fragileTypes) && rand(0, 3) === 0;
    }

    /**
     * Verificar si requiere refrigeración
     */
    private function requiresRefrigeration(array $cargoData): bool
    {
        return $this->isPerishable($cargoData) && rand(0, 1) === 0;
    }

    /**
     * Obtener temperatura mínima
     */
    private function getTemperatureMin(array $cargoData): ?float
    {
        if ($this->requiresRefrigeration($cargoData)) {
            return rand(-5, 5) + (rand(0, 9) / 10);
        }
        return null;
    }

    /**
     * Obtener temperatura máxima
     */
    private function getTemperatureMax(array $cargoData): ?float
    {
        if ($this->requiresRefrigeration($cargoData)) {
            return rand(10, 25) + (rand(0, 9) / 10);
        }
        return null;
    }

    /**
     * Verificar si requiere permiso
     */
    private function requiresPermit(array $cargoData): bool
    {
        $permitTypes = ['chemicals', 'beverages'];
        return in_array($cargoData['cargo_type'], $permitTypes) && rand(0, 2) === 0;
    }

    /**
     * Obtener número de permiso
     */
    private function getPermitNumber(array $cargoData): ?string
    {
        if ($this->requiresPermit($cargoData)) {
            return 'PERMIT-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        }
        return null;
    }

    /**
     * Verificar si requiere inspección
     */
    private function requiresInspection(array $cargoData): bool
    {
        return $this->isDangerousGoods($cargoData) || $this->requiresPermit($cargoData);
    }

    /**
     * Obtener tipo de inspección
     */
    private function getInspectionType(array $cargoData): ?string
    {
        if ($this->requiresInspection($cargoData)) {
            $types = ['safety', 'quality', 'hazmat', 'customs'];
            return $types[array_rand($types)];
        }
        return null;
    }

    /**
     * Generar ID del webservice
     */
    private function generateWebserviceItemId(Shipment $shipment, int $lineNumber): string
    {
        return 'WS' . $shipment->id . '-' . str_pad($lineNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generar datos del webservice
     */
    private function generateWebserviceData(array $cargoData): array
    {
        return [
            'original_ncm' => $cargoData['ncm_code'],
            'validation_status' => 'pending',
            'customs_classification' => 'standard',
            'duty_rate' => rand(0, 35) / 10,
            'tax_exemption' => rand(0, 10) === 0,
            'requires_license' => $this->requiresPermit($cargoData),
            'dangerous_goods' => $this->isDangerousGoods($cargoData),
        ];
    }

    /**
     * Obtener estado del ítem según el shipment
     */
    private function getItemStatus(Shipment $shipment): string
    {
        return match($shipment->status) {
            'planning' => 'draft',
            'loading' => 'validated',
            'loaded', 'in_transit' => 'submitted',
            'arrived', 'discharging' => 'accepted',
            'completed' => 'accepted',
            default => 'draft'
        };
    }

    /**
     * Mostrar resumen de items creados
     */
    private function showSummary(): void
    {
        $totalItems = ShipmentItem::count();
        $byStatus = ShipmentItem::select('status', DB::raw('count(*) as total'))
                                ->groupBy('status')
                                ->pluck('total', 'status')
                                ->toArray();

        $dangerousGoods = ShipmentItem::where('is_dangerous_goods', true)->count();
        $requiresPermit = ShipmentItem::where('requires_permit', true)->count();
        $perishable = ShipmentItem::where('is_perishable', true)->count();
        $refrigerated = ShipmentItem::where('requires_refrigeration', true)->count();
        $fragile = ShipmentItem::where('is_fragile', true)->count();

        // Estadísticas por tipo de carga
        $byCargoType = ShipmentItem::join('cargo_types', 'shipment_items.cargo_type_id', '=', 'cargo_types.id')
                                   ->select('cargo_types.name', DB::raw('count(*) as total'))
                                   ->groupBy('cargo_types.name')
                                   ->pluck('total', 'name')
                                   ->toArray();

        $this->command->line('');
        $this->command->info('📋 RESUMEN DE SHIPMENT ITEMS CREADOS:');
        $this->command->line('');
        $this->command->info("📦 Total items: {$totalItems}");
        $this->command->info("⚠️  Mercancías peligrosas: {$dangerousGoods}");
        $this->command->info("📋 Requieren permiso: {$requiresPermit}");
        $this->command->info("❄️  Perecederos: {$perishable}");
        $this->command->info("🧊 Requieren refrigeración: {$refrigerated}");
        $this->command->info("🔧 Frágiles: {$fragile}");
        
        $this->command->line('');
        $this->command->info('📊 Por estado:');
        foreach ($byStatus as $status => $count) {
            $this->command->line("   • {$status}: {$count}");
        }
        
        $this->command->line('');
        $this->command->info('🏭 Por tipo de carga:');
        foreach ($byCargoType as $type => $count) {
            $this->command->line("   • {$type}: {$count}");
        }
        
        $this->command->line('');
        $this->command->info('✅ SHIPMENT ITEMS 100% coherentes con migración corregida');
        $this->command->info('🔗 JERARQUÍA CORREGIDA: usa shipment_id (no bill_of_lading_id)');
        $this->command->info('📊 Rango: 8-12 items por shipment según solicitado');
        $this->command->info('📈 Datos reales de PARANA.csv implementados');
        $this->command->info('🎯 LISTOS para Bills of Lading y relaciones posteriores');
    }
}