<?php

namespace Database\Factories;

use App\Models\ClientCompanyRelation;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ClientCompanyRelation model
 *
 * Generates relationships between clients and companies with
 * realistic business configurations including permissions,
 * credit limits, and operational settings
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientCompanyRelation>
 */
class ClientCompanyRelationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClientCompanyRelation::class;

    /**
     * Business relationship configurations by type
     */
    private const RELATION_CONFIGS = [
        'customer' => [
            'billing_cycle' => 'monthly',
            'auto_approve_orders' => true,
            'credit_check_required' => false,
            'preferred_payment_method' => 'bank_transfer',
            'notification_preferences' => ['email', 'system'],
        ],
        'provider' => [
            'evaluation_required' => true,
            'quality_standards' => 'ISO_9001',
            'delivery_terms' => 'FOB',
            'payment_terms' => 'NET_30',
            'performance_tracking' => true,
        ],
        'both' => [
            'bilateral_agreement' => true,
            'offset_payments' => true,
            'shared_logistics' => true,
            'joint_operations' => false,
            'priority_handling' => true,
        ]
    ];

    /**
     * Internal code prefixes by company type/role
     */
    private const CODE_PREFIXES = [
        'transport' => 'TRP',
        'shipping' => 'SHP',
        'logistics' => 'LOG',
        'terminal' => 'TER',
        'customs' => 'CUS',
        'warehouse' => 'WHS',
        'freight' => 'FRT',
        'general' => 'GEN'
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get existing clients and companies
        $client = Client::inRandomOrder()->first();
        $company = Company::where('active', true)->inRandomOrder()->first();
        $user = User::inRandomOrder()->first();

        // Ensure we don't create duplicate relations
        $relationType = $this->faker->randomElement(['customer', 'provider', 'both']);

        // Generate realistic credit limits based on relation type
        $creditLimit = $this->generateCreditLimit($relationType);

        // Generate internal code
        $internalCode = $this->generateInternalCode($company, $client);

        // Get relation configuration
        $relationConfig = $this->getRelationConfig($relationType);

        return [
            'client_id' => $client?->id ?? Client::factory(),
            'company_id' => $company?->id ?? Company::factory(),
            'relation_type' => $relationType,
            'can_edit' => $this->faker->boolean(60), // 60% can edit
            'active' => $this->faker->boolean(90), // 90% active
            'credit_limit' => $creditLimit,
            'internal_code' => $internalCode,
            'priority' => $this->faker->numberBetween(1, 10),
            'relation_config' => $relationConfig,
            'created_by_user_id' => $user?->id ?? 1,
            'last_activity_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Indicate that the relation is for a customer
     */
    public function customer(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'relation_type' => 'customer',
                'can_edit' => $this->faker->boolean(40), // Customers have less edit rights
                'credit_limit' => $this->faker->randomFloat(2, 50000, 1000000),
                'priority' => $this->faker->numberBetween(3, 8), // Medium-high priority
                'relation_config' => $this->getRelationConfig('customer'),
            ];
        });
    }

    /**
     * Indicate that the relation is for a provider
     */
    public function provider(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'relation_type' => 'provider',
                'can_edit' => $this->faker->boolean(80), // Providers often can edit
                'credit_limit' => null, // Providers usually don't have credit limits
                'priority' => $this->faker->numberBetween(5, 10), // Higher priority
                'relation_config' => $this->getRelationConfig('provider'),
            ];
        });
    }

    /**
     * Indicate that the relation is bilateral (both customer and provider)
     */
    public function bilateral(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'relation_type' => 'both',
                'can_edit' => $this->faker->boolean(70), // Usually can edit
                'credit_limit' => $this->faker->randomFloat(2, 100000, 2000000), // Higher limits
                'priority' => $this->faker->numberBetween(7, 10), // High priority
                'relation_config' => $this->getRelationConfig('both'),
            ];
        });
    }

    /**
     * Indicate that the relation can edit client data
     */
    public function canEdit(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_edit' => true,
        ]);
    }

    /**
     * Indicate that the relation cannot edit client data
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_edit' => false,
        ]);
    }

    /**
     * Indicate that the relation is active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
            'last_activity_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the relation is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
            'last_activity_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Indicate that the relation has high priority
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(8, 10),
        ]);
    }

    /**
     * Indicate that the relation has low priority
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->numberBetween(1, 3),
        ]);
    }

    /**
     * Indicate that the relation has a high credit limit
     */
    public function highCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => $this->faker->randomFloat(2, 500000, 5000000),
        ]);
    }

    /**
     * Indicate that the relation has no credit limit
     */
    public function noCredit(): static
    {
        return $this->state(fn (array $attributes) => [
            'credit_limit' => null,
        ]);
    }

    /**
     * Create relation with recent activity
     */
    public function recentActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'active' => true,
        ]);
    }

    /**
     * Create relation with old activity
     */
    public function oldActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity_at' => $this->faker->dateTimeBetween('-1 year', '-3 months'),
        ]);
    }

    /**
     * Create relation for specific client and company
     */
    public function forClientAndCompany(Client $client, Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'client_id' => $client->id,
            'company_id' => $company->id,
            'internal_code' => $this->generateInternalCode($company, $client),
        ]);
    }

    /**
     * Create relation with minimal configuration
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'relation_config' => [],
            'credit_limit' => null,
            'priority' => 5,
        ]);
    }

    /**
     * Create relation with full configuration
     */
    public function fullConfig(): static
    {
        return $this->state(function (array $attributes) {
            $relationType = $attributes['relation_type'] ?? 'customer';
            return [
                'relation_config' => array_merge(
                    $this->getRelationConfig($relationType),
                    [
                        'contract_number' => 'CNT-' . $this->faker->numerify('####'),
                        'contract_start_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                        'contract_end_date' => $this->faker->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
                        'special_terms' => $this->faker->sentence(),
                        'contact_person' => $this->faker->name(),
                        'contact_email' => $this->faker->companyEmail(),
                        'contact_phone' => $this->faker->phoneNumber(),
                    ]
                ),
            ];
        });
    }

    /**
     * Generate realistic credit limit based on relation type
     *
     * @param string $relationType
     * @return float|null
     */
    private function generateCreditLimit(string $relationType): ?float
    {
        switch ($relationType) {
            case 'customer':
                // Customers have credit limits for purchases
                return $this->faker->randomFloat(2, 10000, 1000000);

            case 'provider':
                // Providers usually don't have credit limits (they provide credit to us)
                return $this->faker->optional(0.2)->randomFloat(2, 50000, 500000);

            case 'both':
                // Bilateral relations might have higher limits
                return $this->faker->randomFloat(2, 50000, 2000000);

            default:
                return null;
        }
    }

    /**
     * Generate internal code for company-client relation
     *
     * @param Company|null $company
     * @param Client|null $client
     * @return string
     */
    private function generateInternalCode(?Company $company, ?Client $client): string
    {
        if (!$company || !$client) {
            return 'GEN-' . $this->faker->numerify('####');
        }

        // Get prefix based on company type or use first 3 letters of commercial name
        $prefix = $this->getCompanyPrefix($company);

        // Use client ID padded to 4 digits
        $clientNumber = str_pad($client->id ?? rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Add random suffix to avoid conflicts
        $suffix = $this->faker->randomLetter() . $this->faker->randomDigit();

        return strtoupper($prefix . '-' . $clientNumber . $suffix);
    }

    /**
     * Get company prefix for internal codes
     *
     * @param Company $company
     * @return string
     */
    private function getCompanyPrefix(Company $company): string
    {
        $commercialName = strtolower($company->commercial_name ?? $company->business_name);

        // Try to match with known business types
        foreach (self::CODE_PREFIXES as $type => $prefix) {
            if (str_contains($commercialName, $type)) {
                return $prefix;
            }
        }

        // Default to first 3 letters of commercial name
        return strtoupper(substr($company->commercial_name ?? $company->business_name, 0, 3));
    }

    /**
     * Get relation configuration based on type
     *
     * @param string $relationType
     * @return array
     */
    private function getRelationConfig(string $relationType): array
    {
        $baseConfig = self::RELATION_CONFIGS[$relationType] ?? [];

        // Add some variability
        $variations = [
            'auto_notifications' => $this->faker->boolean(70),
            'requires_approval' => $this->faker->boolean(30),
            'emergency_contact' => $this->faker->boolean(20),
            'special_handling' => $this->faker->boolean(15),
        ];

        return array_merge($baseConfig, $variations);
    }
}
