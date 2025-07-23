<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Voyage;
use App\Models\Company;
use App\Models\Vessel;
use App\Models\Captain;
use App\Models\Country;
use App\Models\Port;
use App\Models\CustomOffice;
use App\Models\User;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voyage>
 */
class VoyageFactory extends Factory
{
    protected $model = Voyage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departureDate = $this->faker->dateTimeBetween('-6 months', '+3 months');
        $transitHours = $this->faker->numberBetween(24, 168); // 1-7 días
        $estimatedArrival = (clone $departureDate)->modify("+{$transitHours} hours");
        
        $status = $this->determineVoyageStatus($departureDate, $estimatedArrival);
        
        // Fechas reales si el viaje ya terminó
        $actualArrival = null;
        if (in_array($status, ['completed', 'at_destination'])) {
            $delayHours = $this->faker->boolean(30) ? $this->faker->numberBetween(-6, 48) : 0;
            $actualArrival = (clone $estimatedArrival)->modify("{$delayHours} hours");
        }

        return [
            'voyage_number' => $this->generateVoyageNumber(),
            'internal_reference' => $this->faker->boolean(70) ? strtoupper($this->faker->lexify('REF-????-###')) : null,
            'company_id' => Company::factory(),
            'lead_vessel_id' => Vessel::factory(),
            'captain_id' => $this->faker->boolean(80) ? Captain::factory() : null,
            'origin_country_id' => Country::factory(),
            'origin_port_id' => Port::factory(),
            'destination_country_id' => Country::factory(),
            'destination_port_id' => Port::factory(),
            'transshipment_port_id' => $this->faker->boolean(20) ? Port::factory() : null,
            'origin_customs_id' => $this->faker->boolean(70) ? CustomOffice::factory() : null,
            'destination_customs_id' => $this->faker->boolean(70) ? CustomOffice::factory() : null,
            'transshipment_customs_id' => $this->faker->boolean(10) ? CustomOffice::factory() : null,
            
            // Fechas
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrival,
            'actual_arrival_date' => $actualArrival,
            'customs_clearance_date' => $this->faker->boolean(60) ? 
                $this->faker->dateTimeBetween($departureDate, $estimatedArrival) : null,
            'cargo_loading_start' => $this->faker->dateTimeBetween('-1 day', $departureDate),
            'cargo_loading_end' => $departureDate,
            'cargo_discharge_start' => $actualArrival ? 
                $this->faker->dateTimeBetween($actualArrival, '+1 day') : null,
            'cargo_discharge_end' => $actualArrival ? 
                $this->faker->dateTimeBetween($actualArrival, '+2 days') : null,

            // Tipo y características
            'voyage_type' => $this->faker->randomElement(['single_vessel', 'convoy', 'fleet']),
            'cargo_type' => $this->faker->randomElement(['export', 'import', 'transit', 'transshipment', 'cabotage']),
            'is_consolidated' => $this->faker->boolean(40),
            'has_transshipment' => $this->faker->boolean(20),
            'requires_pilot' => $this->faker->boolean(60),
            'status' => $status,

            // Resumen de carga
            'total_containers' => $this->faker->numberBetween(0, 120),
            'total_cargo_weight' => $this->faker->randomFloat(2, 500, 5000),
            'total_cargo_volume' => $this->faker->randomFloat(2, 1000, 8000),
            'total_bills_of_lading' => $this->faker->numberBetween(1, 15),
            'total_clients' => $this->faker->numberBetween(1, 8),

            // Webservice (Argentina/Paraguay)
            'argentina_voyage_id' => $this->faker->boolean(70) ? 'AR-' . $this->faker->numerify('######') : null,
            'paraguay_voyage_id' => $this->faker->boolean(60) ? 'PY-' . $this->faker->numerify('######') : null,
            'argentina_status' => $this->faker->randomElement(['pending', 'sent', 'approved', 'rejected']),
            'paraguay_status' => $this->faker->randomElement(['pending', 'sent', 'approved']),
            'argentina_sent_at' => $this->faker->boolean(70) ? $this->faker->dateTimeBetween($departureDate, 'now') : null,
            'paraguay_sent_at' => $this->faker->boolean(60) ? $this->faker->dateTimeBetween($departureDate, 'now') : null,

            // Costos financieros
            'estimated_freight_cost' => $this->faker->randomFloat(2, 5000, 50000),
            'actual_freight_cost' => $status === 'completed' ? $this->faker->randomFloat(2, 5000, 55000) : null,
            'fuel_cost' => $this->faker->randomFloat(2, 2000, 15000),
            'port_charges' => $this->faker->randomFloat(2, 500, 3000),
            'currency_code' => $this->faker->randomElement(['USD', 'ARS', 'PYG']),

            // Condiciones y notas
            'weather_conditions' => $this->generateWeatherConditions(),
            'river_conditions' => $this->generateRiverConditions(),
            'voyage_notes' => $this->faker->boolean(60) ? $this->faker->sentence(20) : null,
            'delays_explanation' => $status === 'delayed' ? $this->faker->sentence(15) : null,

            // Documentos
            'required_documents' => $this->generateRequiredDocuments(),
            'uploaded_documents' => $this->generateUploadedDocuments(),
            'customs_approved' => $this->faker->boolean(80),
            'port_authority_approved' => $this->faker->boolean(85),
            'all_documents_ready' => $this->faker->boolean(75),

            // Emergencia y seguridad
            'emergency_contacts' => $this->generateEmergencyContacts(),
            'safety_equipment' => $this->generateSafetyEquipment(),
            'dangerous_cargo' => $this->faker->boolean(15),
            'safety_notes' => $this->faker->boolean(30) ? $this->faker->sentence(10) : null,

            // Performance
            'distance_nautical_miles' => $this->faker->randomFloat(2, 50, 800),
            'average_speed_knots' => $this->faker->randomFloat(2, 8, 15),
            'transit_time_hours' => $transitHours,
            'fuel_consumption' => $this->faker->randomFloat(2, 200, 2000),
            'fuel_efficiency' => $this->faker->randomFloat(2, 0.5, 3.5),

            // Comunicación
            'communication_frequency' => $this->faker->randomElement(['VHF-16', 'VHF-12', 'SATCOM', 'RADIO-MF']),
            'reporting_schedule' => $this->generateReportingSchedule(),
            'last_position_report' => $status === 'in_transit' ? 
                $this->faker->dateTimeBetween('-6 hours', 'now') : null,

            // Flags de estado
            'active' => !in_array($status, ['cancelled', 'completed']),
            'archived' => $this->faker->boolean(10),
            'requires_follow_up' => $this->faker->boolean(25),
            'has_incidents' => $this->faker->boolean(10),

            // Auditoría
            'created_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'created_by_user_id' => $this->faker->boolean(80) ? User::factory() : null,
            'last_updated_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'last_updated_by_user_id' => $this->faker->boolean(80) ? User::factory() : null,
        ];
    }

    /**
     * Indicate that the voyage is in planning status.
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'planning',
            'departure_date' => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'actual_arrival_date' => null,
            'customs_clearance_date' => null,
            'cargo_discharge_start' => null,
            'cargo_discharge_end' => null,
            'active' => true,
        ]);
    }

    /**
     * Indicate that the voyage is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'customs_approved' => true,
            'port_authority_approved' => true,
            'all_documents_ready' => true,
            'active' => true,
        ]);
    }

    /**
     * Indicate that the voyage is in transit.
     */
    public function inTransit(): static
    {
        $departureDate = $this->faker->dateTimeBetween('-3 days', '-1 hour');
        $estimatedArrival = (clone $departureDate)->modify('+' . $this->faker->numberBetween(6, 72) . ' hours');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'in_transit',
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrival,
            'actual_arrival_date' => null,
            'last_position_report' => $this->faker->dateTimeBetween('-6 hours', 'now'),
            'active' => true,
        ]);
    }

    /**
     * Indicate that the voyage is completed.
     */
    public function completed(): static
    {
        $departureDate = $this->faker->dateTimeBetween('-2 months', '-1 week');
        $transitHours = $this->faker->numberBetween(24, 168);
        $estimatedArrival = (clone $departureDate)->modify("+{$transitHours} hours");
        $actualArrival = (clone $estimatedArrival)->modify($this->faker->numberBetween(-6, 24) . ' hours');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrival,
            'actual_arrival_date' => $actualArrival,
            'cargo_discharge_start' => $this->faker->dateTimeBetween($actualArrival, '+1 day'),
            'cargo_discharge_end' => $this->faker->dateTimeBetween($actualArrival, '+2 days'),
            'actual_freight_cost' => $this->faker->randomFloat(2, 5000, 55000),
            'active' => false,
        ]);
    }

    /**
     * Indicate that the voyage is delayed.
     */
    public function delayed(): static
    {
        $departureDate = $this->faker->dateTimeBetween('-1 week', '-1 day');
        $estimatedArrival = $this->faker->dateTimeBetween('-2 days', '+1 day');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'delayed',
            'departure_date' => $departureDate,
            'estimated_arrival_date' => $estimatedArrival,
            'delays_explanation' => $this->faker->randomElement([
                'Condiciones climáticas adversas',
                'Problemas mecánicos menores',
                'Demoras en el puerto de origen',
                'Restricciones de navegación',
                'Congestión en el canal de navegación'
            ]),
            'requires_follow_up' => true,
            'active' => true,
        ]);
    }

    /**
     * Indicate that the voyage is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'actual_arrival_date' => null,
            'cargo_discharge_start' => null,
            'cargo_discharge_end' => null,
            'active' => false,
            'requires_follow_up' => false,
        ]);
    }

    /**
     * Indicate that the voyage has dangerous cargo.
     */
    public function withDangerousCargo(): static
    {
        return $this->state(fn (array $attributes) => [
            'dangerous_cargo' => true,
            'requires_pilot' => true,
            'safety_notes' => 'Carga peligrosa a bordo - Seguir protocolos especiales',
        ]);
    }

    /**
     * Indicate that the voyage is a convoy type.
     */
    public function convoy(): static
    {
        return $this->state(fn (array $attributes) => [
            'voyage_type' => 'convoy',
            'total_containers' => $this->faker->numberBetween(60, 200),
            'total_cargo_weight' => $this->faker->randomFloat(2, 2000, 8000),
            'requires_pilot' => true,
        ]);
    }

    /**
     * Indicate that the voyage is for export.
     */
    public function export(): static
    {
        return $this->state(fn (array $attributes) => [
            'cargo_type' => 'export',
            'argentina_voyage_id' => 'AR-' . $this->faker->numerify('######'),
            'argentina_status' => 'approved',
        ]);
    }

    /**
     * Indicate that the voyage is for import.
     */
    public function import(): static
    {
        return $this->state(fn (array $attributes) => [
            'cargo_type' => 'import',
            'paraguay_voyage_id' => 'PY-' . $this->faker->numerify('######'),
            'paraguay_status' => 'approved',
        ]);
    }

    // Private helper methods
    private function determineVoyageStatus($departureDate, $estimatedArrival): string
    {
        $now = now();
        $departure = Carbon::parse($departureDate);
        $arrival = Carbon::parse($estimatedArrival);

        if ($departure->isFuture()) {
            return $this->faker->randomElement(['planning', 'approved']);
        } elseif ($departure->isPast() && $arrival->isFuture()) {
            return $this->faker->randomElement(['in_transit', 'delayed']);
        } elseif ($arrival->isPast()) {
            return $this->faker->randomElement(['completed', 'at_destination']);
        }
        
        return 'approved';
    }

    private function generateVoyageNumber(): string
    {
        return strtoupper($this->faker->lexify('VOY-???-###-' . date('y')));
    }

    private function generateWeatherConditions(): array
    {
        return [
            'temperature' => $this->faker->numberBetween(15, 35),
            'humidity' => $this->faker->numberBetween(60, 90),
            'wind_speed' => $this->faker->numberBetween(5, 25),
            'wind_direction' => $this->faker->randomElement(['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']),
            'visibility' => $this->faker->randomElement(['excellent', 'good', 'moderate', 'poor']),
            'precipitation' => $this->faker->randomElement(['none', 'light', 'moderate', 'heavy']),
            'conditions' => $this->faker->randomElement([
                'clear', 'partly_cloudy', 'cloudy', 'overcast', 'fog', 'rain', 'storm'
            ]),
        ];
    }

    private function generateRiverConditions(): array
    {
        return [
            'water_level' => $this->faker->randomFloat(2, 1.5, 6.0),
            'current_speed' => $this->faker->randomFloat(2, 0.5, 3.0),
            'navigability' => $this->faker->randomElement(['excellent', 'good', 'moderate', 'restricted']),
            'obstacles' => $this->faker->boolean(20) ? $this->faker->randomElement([
                'floating_debris', 'sandbanks', 'low_bridges', 'shallow_areas'
            ]) : 'none',
            'tide_level' => $this->faker->randomElement(['high', 'medium', 'low']),
            'water_temperature' => $this->faker->numberBetween(18, 28),
        ];
    }

    private function generateRequiredDocuments(): array
    {
        return [
            'manifesto_carga',
            'conocimiento_embarque',
            'certificado_origen',
            'factura_comercial',
            'lista_empaque',
            'declaracion_aduana',
            'certificado_fumigacion',
            'poliza_seguro',
            'permiso_navegacion',
            'certificado_seguridad'
        ];
    }

    private function generateUploadedDocuments(): array
    {
        $required = $this->generateRequiredDocuments();
        $uploaded = [];
        
        foreach ($required as $doc) {
            if ($this->faker->boolean(75)) {
                $uploaded[] = [
                    'document_type' => $doc,
                    'filename' => $doc . '_' . $this->faker->uuid . '.pdf',
                    'uploaded_at' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s'),
                    'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
                ];
            }
        }
        
        return $uploaded;
    }

    private function generateEmergencyContacts(): array
    {
        return [
            [
                'name' => $this->faker->name,
                'role' => 'Capitán de Puerto',
                'phone' => $this->faker->phoneNumber,
                'email' => $this->faker->email,
                'available_24h' => true,
            ],
            [
                'name' => $this->faker->name,
                'role' => 'Coordinador de Flota',
                'phone' => $this->faker->phoneNumber,
                'email' => $this->faker->email,
                'available_24h' => true,
            ],
        ];
    }

    private function generateSafetyEquipment(): array
    {
        return [
            'life_jackets' => $this->faker->numberBetween(10, 50),
            'fire_extinguishers' => $this->faker->numberBetween(5, 15),
            'emergency_flares' => $this->faker->numberBetween(6, 12),
            'first_aid_kits' => $this->faker->numberBetween(2, 5),
            'life_rafts' => $this->faker->numberBetween(1, 4),
            'emergency_radio' => true,
            'gps_backup' => true,
        ];
    }

    private function generateReportingSchedule(): array
    {
        return [
            'departure_report' => '06:00',
            'morning_report' => '08:00',
            'noon_report' => '12:00',
            'evening_report' => '18:00',
            'arrival_report' => 'on_arrival',
            'emergency_frequency' => 'VHF-16',
            'position_interval_hours' => 4,
        ];
    }
}