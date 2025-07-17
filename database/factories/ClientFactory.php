<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\CustomOffice;
use App\Models\Port;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Client model
 *
 * Generates realistic client data for testing and seeding
 * Supports both Argentina (CUIT) and Paraguay (RUC) tax IDs
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Obtener países disponibles (Argentina por defecto)
        $country = Country::where('alpha2_code', 'AR')->first() ?? Country::first();
        $countryId = $country?->id ?? 1;
        $isArgentina = $country?->alpha2_code === 'AR';

        // Generar tax_id válido según país
        $taxId = $isArgentina ? $this->generateValidCUIT() : $this->generateValidRUC();

        // Obtener tipo de documento apropiado para el país
        $documentType = DocumentType::where('country_id', $countryId)->first();

        // Obtener puerto y aduana del país
        $port = Port::where('country_id', $countryId)->inRandomOrder()->first();
        $customOffice = CustomOffice::where('country_id', $countryId)->inRandomOrder()->first();

        // Obtener empresa existente para created_by_company_id
        $company = Company::inRandomOrder()->first();

        // Generar nombres de empresas realistas según país
        $companyNames = $isArgentina ? [
            'ALUAR S.A.',
            'Transportes Fluviales del Plata S.A.',
            'Cargas Marítimas Argentina S.R.L.',
            'Logística Portuaria S.A.',
            'Terminal Marítima Buenos Aires S.A.',
            'Navegación y Cargas del Sur S.A.',
            'Contenedores del Río S.R.L.',
            'Multipropósito Logístico S.A.',
        ] : [
            'Navegación Paraguay S.A.',
            'Transportes del Este S.R.L.',
            'Cargas Fluviales Paraguay S.A.',
            'Logística Asunción S.A.',
            'Terminal Portuaria del Este S.A.',
            'Contenedores Paraguay S.R.L.',
            'Multimodal Paraguay S.A.',
            'Cargas Internacionales S.R.L.',
        ];

        return [
            'tax_id' => $taxId,
            'country_id' => $countryId,
            'document_type_id' => $documentType?->id ?? 1,
            'client_type' => $this->faker->randomElement(['shipper', 'consignee', 'notify_party', 'owner']),
            'legal_name' => $this->faker->randomElement($companyNames),
            'primary_port_id' => $port?->id,
            'customs_offices_id' => $customOffice?->id,
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'inactive']), // 75% activos
            'created_by_company_id' => $company?->id ?? 1,
            'verified_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 year', 'now'), // 70% verificados
            'notes' => $this->faker->optional(0.3)->realText(200), // 30% con notas
        ];
    }

    /**
     * Indicate that the client is from Argentina
     */
    public function argentina(): static
    {
        return $this->state(function (array $attributes) {
            $country = Country::where('alpha2_code', 'AR')->first();
            $documentType = DocumentType::where('country_id', $country?->id)->first();
            $port = Port::where('country_id', $country?->id)->inRandomOrder()->first();
            $customOffice = CustomOffice::where('country_id', $country?->id)->inRandomOrder()->first();

            return [
                'tax_id' => $this->generateValidCUIT(),
                'country_id' => $country?->id ?? 1,
                'document_type_id' => $documentType?->id ?? 1,
                'primary_port_id' => $port?->id,
                'customs_offices_id' => $customOffice?->id,
                'legal_name' => $this->faker->randomElement([
                    'ALUAR SOCIEDAD ANONIMA',
                    'SIDERAR S.A.I.C.',
                    'TENARIS S.A.',
                    'ARCELOR MITTAL ACINDAR S.A.',
                    'MOLINOS RIO DE LA PLATA S.A.',
                    'BUNGE ARGENTINA S.A.',
                    'CARGILL S.A.C.I.',
                    'DREYFUS ARGENTINA S.A.',
                ]),
            ];
        });
    }

    /**
     * Indicate that the client is from Paraguay
     */
    public function paraguay(): static
    {
        return $this->state(function (array $attributes) {
            $country = Country::where('alpha2_code', 'PY')->first();
            $documentType = DocumentType::where('country_id', $country?->id)->first();
            $port = Port::where('country_id', $country?->id)->inRandomOrder()->first();
            $customOffice = CustomOffice::where('country_id', $country?->id)->inRandomOrder()->first();

            return [
                'tax_id' => $this->generateValidRUC(),
                'country_id' => $country?->id ?? 2,
                'document_type_id' => $documentType?->id ?? 2,
                'primary_port_id' => $port?->id,
                'customs_offices_id' => $customOffice?->id,
                'legal_name' => $this->faker->randomElement([
                    'PETROPAR S.A.',
                    'COPACO S.A.',
                    'INC S.A.',
                    'ACEPAR S.A.',
                    'CAPIATÁ S.A.',
                    'FRIGOMERC S.A.',
                    'CONTI PARAGUAY S.A.',
                    'MINERVA FOODS PARAGUAY S.A.',
                ]),
            ];
        });
    }

    /**
     * Indicate that the client is a shipper
     */
    public function shipper(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => 'shipper',
        ]);
    }

    /**
     * Indicate that the client is a consignee
     */
    public function consignee(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => 'consignee',
        ]);
    }

    /**
     * Indicate that the client is a notify party
     */
    public function notifyParty(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => 'notify_party',
        ]);
    }

    /**
     * Indicate that the client is an owner
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'client_type' => 'owner',
        ]);
    }

    /**
     * Indicate that the client is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'verified_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Indicate that the client is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the client is suspended
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'notes' => 'Cliente suspendido por ' . $this->faker->randomElement([
                'documentación pendiente',
                'verificación de datos',
                'solicitud de la empresa',
                'mantenimiento de sistema'
            ]),
        ]);
    }

    /**
     * Indicate that the client is verified
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the client is unverified
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }

    /**
     * Generate a valid CUIT for Argentina (11 digits with check digit)
     *
     * @return string
     */
    private function generateValidCUIT(): string
    {
        // Generar los primeros 10 dígitos
        $firstDigits = str_pad((string) $this->faker->numberBetween(20000000, 30999999), 10, '0', STR_PAD_LEFT);

        // Calcular dígito verificador usando algoritmo mod11
        $multipliers = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $firstDigits[$i] * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return $firstDigits . $checkDigit;
    }

    /**
     * Generate a valid RUC for Paraguay (7-8 digits + check digit)
     *
     * @return string
     */
    private function generateValidRUC(): string
    {
        // Generar número base de 7-8 dígitos
        $baseNumber = str_pad((string) $this->faker->numberBetween(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        // Calcular dígito verificador para RUC paraguayo
        $multipliers = [2, 3, 4, 5, 6, 7, 2];
        $sum = 0;

        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $baseNumber[$i] * $multipliers[$i];
        }

        $remainder = $sum % 11;
        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;

        return $baseNumber . '-' . $checkDigit;
    }
}
