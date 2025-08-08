<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'active' => true,
            'timezone' => 'America/Argentina/Buenos_Aires',
        ];
    }

    /**
     * Super admin user
     */
    public function superAdmin(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Super Admin',
                'email' => 'admin@cargas.com',
                'userable_type' => null,
                'userable_id' => null,
            ];
        })->afterCreating(function (User $user) {
            $user->assignRole('super-admin');
        });
    }

    /**
     * Company admin user
     */
    public function companyAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $company = Company::factory()->create();
            $user->update([
                'userable_type' => 'App\Models\Company',
                'userable_id' => $company->id,
            ]);
            $user->assignRole('company-admin');
        });
    }

    

    /**
     * External operator user
     */
    public function externalOperator(): static
    {
        return $this->afterCreating(function (User $user) {
            $operator = Operator::factory()->external()->create();
            $user->update([
                'userable_type' => 'App\Models\Operator',
                'userable_id' => $operator->id,
            ]);
            $user->assignRole('external-operator');
        });
    }

    /**
     * Unverified email
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Inactive user
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}

// database/factories/CompanyFactory.php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->company(),
            'commercial_name' => fake()->companySuffix(),
            'tax_id' => fake()->numerify('###########'),
            'country' => fake()->randomElement(['AR', 'PY']),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'ws_config' => [
                'argentina' => [
                    'url_anticipada' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
                    'url_micdta' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms',
                ],
                'paraguay' => [
                    'url_ata' => 'https://ws.aduana.gov.py/ata/services/LoginCms',
                ],
            ],
            'ws_active' => true,
            'ws_environment' => 'testing',
            'active' => true,
            'created_date' => now(),
            'certificate_expires_at' => now()->addYear(),
        ];
    }

    /**
     * Argentina company
     */
    public function argentina(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'AR',
            'tax_id' => '20' . fake()->numerify('#########'),
        ]);
    }

    /**
     * Paraguay company
     */
    public function paraguay(): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => 'PY',
            'tax_id' => '80' . fake()->numerify('#########'),
        ]);
    }

    /**
     * Inactive company
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Expired certificate
     */
    public function expiredCertificate(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_expires_at' => now()->subDays(30),
        ]);
    }

    /**
     * With valid certificate
     */
    public function withValidCertificate(): static
    {
        return $this->state(fn (array $attributes) => [
            'certificate_path' => 'certificates/cert_' . fake()->uuid() . '.p12',
                            'certificate_password' => encrypt('password123'),
                            'certificate_alias' => fake()->word(),
                            'certificate_expires_at' => now()->addYear(),
        ]);
    }
}

// database/factories/OperatorFactory.php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_number' => fake()->numerify('########'),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->jobTitle(),
            'type' => 'external',
            'company_id' => Company::factory(),
            'special_permissions' => [],
            'can_import' => fake()->boolean(),
            'can_export' => fake()->boolean(),
            'can_transfer' => fake()->boolean(),
            'active' => true,
            'created_date' => now(),
        ];
    }

    /**
     * Internal operator
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'internal',
            'company_id' => null,
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
        ]);
    }

    /**
     * External operator
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'external',
            'company_id' => Company::factory(),
        ]);
    }

    /**
     * Inactive operator
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * With all permissions
     */
    public function withAllPermissions(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_import' => true,
            'can_export' => true,
            'can_transfer' => true,
            'special_permissions' => ['advanced_reports', 'bulk_operations'],
        ]);
    }

    /**
     * With specific company
     */
    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $company->id,
            'type' => 'external',
        ]);
    }
}
