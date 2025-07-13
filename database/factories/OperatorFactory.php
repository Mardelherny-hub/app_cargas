<?php

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
            'type' => 'external', // CORREGIDO: Solo operadores externos
            'company_id' => Company::factory(), // CORREGIDO: Siempre tiene empresa
            'special_permissions' => [],
            'can_import' => fake()->boolean(),
            'can_export' => fake()->boolean(),
            'can_transfer' => fake()->boolean(),
            'active' => true,
            'created_date' => now(),
        ];
    }

    /**
     * ELIMINADO: El método internal() ya que no existen operadores internos
     */

    /**
     * External operator (método mantenido para compatibilidad)
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

    /**
     * With specific permissions
     */
    public function withPermissions(bool $import = false, bool $export = false, bool $transfer = false): static
    {
        return $this->state(fn (array $attributes) => [
            'can_import' => $import,
            'can_export' => $export,
            'can_transfer' => $transfer,
        ]);
    }

    /**
     * Import only operator
     */
    public function importOnly(): static
    {
        return $this->withPermissions(import: true);
    }

    /**
     * Export only operator
     */
    public function exportOnly(): static
    {
        return $this->withPermissions(export: true);
    }

    /**
     * Transfer only operator
     */
    public function transferOnly(): static
    {
        return $this->withPermissions(transfer: true);
    }

    /**
     * Full permissions operator
     */
    public function fullPermissions(): static
    {
        return $this->withPermissions(import: true, export: true, transfer: true);
    }
}
