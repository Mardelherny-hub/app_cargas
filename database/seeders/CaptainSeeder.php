<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Voyage;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Country;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;

class VoyageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('es_AR');
        
        // Obtener datos de referencia existentes
        $companies = Company::all();
        $vessels = Vessel::all();
        $captains = Captain::all();
        $countries = Country::all();
        $ports = Port::all();
        $customsOffices = CustomOffice::all();
        $users = User::all();

        if ($companies->isEmpty() || $vessels->isEmpty() || $countries->isEmpty() || $ports->isEmpty()) {
            $this->command->warn('No se encontraron datos de referencia suficientes. Ejecuta primero los seeders de companies, vessels, countries y ports.');
            return;
        }

        // Rutas típicas del transporte fluvial en la región
        $commonRoutes = $this->getCommonRoutes($countries, $ports);
        
        // Crear 50 viajes de prueba
        for ($i = 1; $i <= 50; $i++) {
            $company = $companies->random();
            $vessel = $vessels->random();
            $captain = $captains->isNotEmpty() ? $captains->random() : null;
            $route = $faker->randomElement($commonRoutes);
            $user = $users->isNotEmpty() ? $users->random() : null;

            // Fechas del viaje
            $departureDate = $faker->dateTimeBetween('-6 months', '+3 months');
            $transitHours = $faker->numberBetween(24, 168); // 1-7 días
            $estimatedArrival = (clone $departureDate)->modify("+{$transitHours} hours");
            
            // Estado del viaje basado en la fecha
            $status = $this->determineVoyageStatus($departureDate, $estimatedArrival);
            
            // Fechas reales si el viaje ya terminó
            $actualArrival = null;
            if (in_array($status, ['completed', 'at_destination'])) {
                $delayHours = $faker->boolean(30) ? $faker->numberBetween(-6, 48) : 0;
                $actualArrival = (clone $estimatedArrival)->modify("{$delayHours} hours");
            }

            // Datos de carga
            $totalContainers = $faker->numberBetween(0, 120);
            $totalWeight = $faker->randomFloat(2, 500, 5000); // toneladas
            $totalVolume = $faker->randomFloat(2, 1000, 8000); // m³
            
            // Tipo de viaje basado en el tipo de embarcación
            $voyageType = $this->getVoyageTypeByVessel($vessel);
            
            // Generar número de viaje
            $voyageNumber = $this->generateVoyageNumber($company, $departureDate, $i);

            $voyage = Voyage::create([
                'voyage_number' => $voyageNumber,
                'internal_reference' => $faker->boolean(70) ? strtoupper($faker->lexify('REF-????-###')) : null,
                'company_id' => $company->id,
                'lead_vessel_id' => $vessel->id,
                'captain_id' => $captain?->id,
                'origin_country_id' => $route['origin_country_id'],
                'origin_port_id' => $route['origin_port_id'],
                'destination_country_id' => $route['destination_country_id'],
                'destination_port_id' => $route['destination_port_id'],
                'transshipment_port_id' => $faker->boolean(20) ? $ports->random()->id : null,
                'origin_customs_id' => $customsOffices->isNotEmpty() ? $customsOffices->random()->id : null,
                'destination_customs_id' => $customsOffices->isNotEmpty() ? $customsOffices->random()->id : null,
                
                // Fechas
                'departure_date' => $departureDate,
                'estimated_arrival_date' => $estimatedArrival,
                'actual_arrival_date' => $actualArrival,
                'customs_clearance_date' => $faker->boolean(60) ? 
                    $faker->dateTimeBetween($departureDate, $estimatedArrival) : null,
                'cargo_loading_start' => $faker->dateTimeBetween('-1 day', $departureDate),
                'cargo_loading_end' => $departureDate,
                'cargo_discharge_start' => $actualArrival ? 
                    $faker->dateTimeBetween($actualArrival, '+1 day') : null,
                'cargo_discharge_end' => $actualArrival ? 
                    $faker->dateTimeBetween($actualArrival, '+2 days') : null,

                // Tipo y características
                'voyage_type' => $voyageType,
                'cargo_type' => $faker->randomElement(['export', 'import', 'transit', 'transshipment', 'cabotage']),
                'is_consolidated' => $faker->boolean(40),
                'has_transshipment' => $faker->boolean(20),
                'requires_pilot' => $faker->boolean(60),
                'status' => $status,

                // Resumen de carga
                'total_containers' => $totalContainers,
                'total_cargo_weight' => $totalWeight,
                'total_cargo_volume' => $totalVolume,
                'total_bills_of_lading' => $faker->numberBetween(1, 15),
                'total_clients' => $faker->numberBetween(1, 8),

                // Webservice (Argentina/Paraguay)
                'argentina_voyage_id' => $faker->boolean(70) ? 'AR-' . $faker->numerify('######') : null,
                'paraguay_voyage_id' => $faker->boolean(60) ? 'PY-' . $faker->numerify('######') : null,
                'argentina_status' => $faker->randomElement(['pending', 'sent', 'approved', 'rejected']),
                'paraguay_status' => $faker->randomElement(['pending', 'sent', 'approved']),
                'argentina_sent_at' => $faker->boolean(70) ? $faker->dateTimeBetween($departureDate, 'now') : null,
                'paraguay_sent_at' => $faker->boolean(60) ? $faker->dateTimeBetween($departureDate, 'now') : null,

                // Costos financieros
                'estimated_freight_cost' => $faker->randomFloat(2, 5000, 50000),
                'actual_freight_cost' => $status === 'completed' ? $faker->randomFloat(2, 5000, 55000) : null,
                'fuel_cost' => $faker->randomFloat(2, 2000, 15000),
                'port_charges' => $faker->randomFloat(2, 500, 3000),
                'currency_code' => $faker->randomElement(['USD', 'ARS', 'PYG']),

                // Condiciones y notas
                'weather_conditions' => $this->generateWeatherConditions($faker),
                'river_conditions' => $this->generateRiverConditions($faker),
                'voyage_notes' => $faker->boolean(60) ? $faker->sentence(20) : null,
                'delays_explanation' => $status === 'delayed' ? $faker->sentence(15) : null,

                // Documentos
                'required_documents' => $this->generateRequiredDocuments(),
                'uploaded_documents' => $this->generateUploadedDocuments($faker),
                'customs_approved' => $faker->boolean(80),
                'port_authority_approved' => $faker->boolean(85),
                'all_documents_ready' => $faker->boolean(75),

                // Emergencia y seguridad
                'emergency_contacts' => $this->generateEmergencyContacts($faker),
                'safety_equipment' => $this->generateSafetyEquipment($faker),
                'dangerous_cargo' => $faker->boolean(15),
                'safety_notes' => $faker->boolean(30) ? $faker->sentence(10) : null,

                // Performance
                'distance_nautical_miles' => $faker->randomFloat(2, 50, 800),
                'average_speed_knots' => $faker->randomFloat(2, 8, 15),
                'transit_time_hours' => $transitHours,
                'fuel_consumption' => $faker->randomFloat(2, 200, 2000),
                'fuel_efficiency' => $faker->randomFloat(2, 0.5, 3.5),

                // Comunicación
                'communication_frequency' => $faker->randomElement(['VHF-16', 'VHF-12', 'SATCOM', 'RADIO-MF']),
                'reporting_schedule' => $this->generateReportingSchedule(),
                'last_position_report' => $status === 'in_transit' ? 
                    $faker->dateTimeBetween('-6 hours', 'now') : null,

                // Flags de estado
                'active' => !in_array($status, ['cancelled', 'completed']),
                'archived' => $faker->boolean(10),
                'requires_follow_up' => $faker->boolean(25),
                'has_incidents' => $faker->boolean(10),

                // Auditoría
                'created_date' => $faker->dateTimeBetween('-6 months', 'now'),
                'created_by_user_id' => $user?->id,
                'last_updated_date' => $faker->dateTimeBetween('-1 month', 'now'),
                'last_updated_by_user_id' => $user?->id,
            ]);

            // Calcular costo total
            $voyage->updateTotalCost();
        }

        $this->command->info('✅ Se crearon 50 viajes de prueba exitosamente.');
    }

    private function getCommonRoutes($countries, $ports): array
    {
        // Intentar obtener países conocidos
        $argentina = $countries->where('name', 'like', '%Argentina%')->first() 
                   ?? $countries->where('iso_code', 'AR')->first()
                   ?? $countries->first();
                   
        $paraguay = $countries->where('name', 'like', '%Paraguay%')->first()
                  ?? $countries->where('iso_code', 'PY')->first()
                  ?? $countries->skip(1)->first() ?? $countries->first();

        $uruguay = $countries->where('name', 'like', '%Uruguay%')->first()
                 ?? $countries->where('iso_code', 'UY')->first()
                 ?? $countries->skip(2)->first() ?? $countries->first();

        $brasil = $countries->where('name', 'like', '%Brasil%')->first()
                ?? $countries->where('name', 'like', '%Brazil%')->first()
                ?? $countries->where('iso_code', 'BR')->first()
                ?? $countries->skip(3)->first() ?? $countries->first();

        // Intentar obtener puertos conocidos o usar los disponibles
        $availablePorts = $ports->take(10); // Usar los primeros 10 puertos disponibles
        
        if ($availablePorts->count() < 4) {
            // Si no hay suficientes puertos, crear rutas con los disponibles
            return $this->createBasicRoutes($availablePorts, $argentina, $paraguay);
        }

        return [
            // Rutas Argentina-Paraguay
            [
                'origin_country_id' => $argentina->id,
                'origin_port_id' => $availablePorts->get(0)->id,
                'destination_country_id' => $paraguay->id,
                'destination_port_id' => $availablePorts->get(1)->id,
            ],
            [
                'origin_country_id' => $argentina->id,
                'origin_port_id' => $availablePorts->get(2)->id,
                'destination_country_id' => $paraguay->id,
                'destination_port_id' => $availablePorts->get(1)->id,
            ],
            // Rutas Argentina-Uruguay
            [
                'origin_country_id' => $argentina->id,
                'origin_port_id' => $availablePorts->get(0)->id,
                'destination_country_id' => $uruguay->id,
                'destination_port_id' => $availablePorts->get(3)->id,
            ],
            // Rutas Argentina-Brasil
            [
                'origin_country_id' => $argentina->id,
                'origin_port_id' => $availablePorts->get(2)->id,
                'destination_country_id' => $brasil->id,
                'destination_port_id' => $availablePorts->get(4)->id ?? $availablePorts->get(0)->id,
            ],
            // Rutas de cabotaje Argentina
            [
                'origin_country_id' => $argentina->id,
                'origin_port_id' => $availablePorts->get(0)->id,
                'destination_country_id' => $argentina->id,
                'destination_port_id' => $availablePorts->get(2)->id,
            ],
        ];
    }

    private function createBasicRoutes($ports, $country1, $country2): array
    {
        $routes = [];
        $portArray = $ports->toArray();
        
        for ($i = 0; $i < count($portArray) - 1; $i++) {
            $routes[] = [
                'origin_country_id' => $country1->id,
                'origin_port_id' => $portArray[$i]['id'],
                'destination_country_id' => $country2->id,
                'destination_port_id' => $portArray[$i + 1]['id'],
            ];
        }
        
        return $routes;
    }

    private function determineVoyageStatus($departureDate, $estimatedArrival): string
    {
        $now = now();
        $departure = Carbon::parse($departureDate);
        $arrival = Carbon::parse($estimatedArrival);

        if ($departure->isFuture()) {
            return 'planning';
        } elseif ($departure->isPast() && $arrival->isFuture()) {
            return 'in_transit';
        } elseif ($arrival->isPast()) {
            return collect(['completed', 'at_destination'])->random();
        }
        
        return 'approved';
    }

    private function getVoyageTypeByVessel($vessel): string
    {
        // Basado en el tipo de embarcación, determinar el tipo de viaje
        $vesselName = strtolower($vessel->name ?? '');
        
        if (str_contains($vesselName, 'remolcador') || str_contains($vesselName, 'convoy')) {
            return 'convoy';
        } elseif (str_contains($vesselName, 'flota') || str_contains($vesselName, 'fleet')) {
            return 'fleet';
        }
        
        return 'single_vessel';
    }

    private function generateVoyageNumber($company, $departureDate, $sequence): string
    {
        $date = Carbon::parse($departureDate);
        $companyCode = $company->code ?? 'VOY';
        $dateStr = $date->format('ymd');
        
        return sprintf('%s-%s-%03d', strtoupper($companyCode), $dateStr, $sequence);
    }

    private function generateWeatherConditions($faker): array
    {
        return [
            'temperature' => $faker->numberBetween(15, 35),
            'humidity' => $faker->numberBetween(60, 95),
            'wind_speed' => $faker->numberBetween(5, 25),
            'wind_direction' => $faker->randomElement(['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']),
            'visibility' => $faker->randomElement(['Excelente', 'Buena', 'Regular', 'Limitada']),
            'precipitation' => $faker->randomElement(['Ninguna', 'Llovizna', 'Lluvia ligera', 'Lluvia intensa']),
            'conditions' => $faker->randomElement(['Despejado', 'Parcialmente nublado', 'Nublado', 'Tormentoso'])
        ];
    }

    private function generateRiverConditions($faker): array
    {
        return [
            'water_level' => $faker->randomFloat(2, 2.5, 8.0), // metros
            'current_speed' => $faker->randomFloat(2, 1.0, 4.5), // nudos
            'depth' => $faker->randomFloat(2, 8.0, 25.0), // metros
            'navigation_status' => $faker->randomElement(['Normal', 'Restringida', 'Precaución', 'Cerrada']),
            'obstacles' => $faker->boolean(20) ? $faker->randomElement(['Troncos', 'Sedimentos', 'Bajo nivel', 'Ninguno']) : 'Ninguno',
            'tidal_conditions' => $faker->randomElement(['Alta', 'Baja', 'Creciente', 'Menguante']),
            'temperature_water' => $faker->numberBetween(18, 28)
        ];
    }

    private function generateRequiredDocuments(): array
    {
        return [
            'bill_of_lading' => true,
            'cargo_manifest' => true,
            'customs_declaration' => true,
            'vessel_certificate' => true,
            'crew_documents' => true,
            'insurance_certificate' => true,
            'port_clearance' => true,
            'dangerous_goods_declaration' => false,
            'phytosanitary_certificate' => false,
            'origin_certificate' => true
        ];
    }

    private function generateUploadedDocuments($faker): array
    {
        $documents = [];
        $docTypes = [
            'bill_of_lading', 'cargo_manifest', 'customs_declaration', 
            'vessel_certificate', 'crew_documents', 'insurance_certificate'
        ];

        foreach ($docTypes as $docType) {
            if ($faker->boolean(75)) {
                $documents[$docType] = [
                    'filename' => $docType . '_' . $faker->numberBetween(1000, 9999) . '.pdf',
                    'uploaded_at' => $faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                    'uploaded_by' => $faker->name(),
                    'file_size' => $faker->numberBetween(100, 5000) . 'KB',
                    'status' => $faker->randomElement(['approved', 'pending', 'rejected'])
                ];
            }
        }

        return $documents;
    }

    private function generateEmergencyContacts($faker): array
    {
        return [
            'primary' => [
                'name' => $faker->name(),
                'position' => 'Coordinador Operaciones',
                'phone' => $faker->phoneNumber(),
                'email' => $faker->safeEmail(),
                'available_24h' => true
            ],
            'secondary' => [
                'name' => $faker->name(),
                'position' => 'Gerente Flota',
                'phone' => $faker->phoneNumber(),
                'email' => $faker->safeEmail(),
                'available_24h' => false
            ],
            'port_authority' => [
                'name' => 'Autoridad Portuaria',
                'phone' => '+54-11-4000-0000',
                'radio' => 'VHF Canal 16',
                'emergency_frequency' => '2182 kHz'
            ]
        ];
    }

    private function generateSafetyEquipment($faker): array
    {
        return [
            'life_jackets' => $faker->numberBetween(10, 50),
            'life_rafts' => $faker->numberBetween(2, 8),
            'fire_extinguishers' => $faker->numberBetween(5, 15),
            'emergency_radio' => true,
            'flares' => $faker->numberBetween(6, 24),
            'first_aid_kit' => true,
            'emergency_lighting' => true,
            'immersion_suits' => $faker->numberBetween(4, 12),
            'epirb' => true, // Emergency Position Indicating Radio Beacon
            'ais_transponder' => true,
            'radar' => true,
            'gps' => true,
            'last_inspection' => $faker->dateTimeBetween('-6 months', '-1 month')->format('Y-m-d')
        ];
    }

    private function generateReportingSchedule(): array
    {
        return [
            'departure_report' => '06:00',
            'position_reports' => ['12:00', '18:00', '00:00'],
            'arrival_eta_update' => '06:00',
            'emergency_frequency' => 'Continuo',
            'weather_reports' => ['06:00', '18:00'],
            'cargo_status' => ['12:00'],
            'fuel_status' => 'Diario 18:00'
        ];
    }
}