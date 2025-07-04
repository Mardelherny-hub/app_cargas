<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class FixPolymorphicRelationshipsCommand extends Command
{
    protected $signature = 'users:fix-polymorphic {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix broken polymorphic relationships in users table';

    public function handle()
    {
        $this->info('=== FIXING POLYMORPHIC RELATIONSHIPS ===');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // 1. Find broken relationships
        $this->findBrokenRelationships($dryRun);

        // 2. Find orphaned entities
        $this->findOrphanedEntities($dryRun);

        // 3. Summary
        $this->displaySummary();

        if ($dryRun) {
            $this->newLine();
            $this->info('Run without --dry-run to apply the fixes');
        }
    }

    private function findBrokenRelationships($dryRun)
    {
        $this->info('1. Finding broken user relationships...');

        // Find users with userable_type but no corresponding record
        $brokenUsers = User::whereNotNull('userable_type')
        ->whereNotNull('userable_id')
        ->whereDoesntHave('userable')
        ->get();

        if ($brokenUsers->isEmpty()) {
            $this->info('  ✅ No broken relationships found');
            return;
        }

        $this->error("  ❌ Found {$brokenUsers->count()} broken relationships");
        $this->newLine();

        foreach ($brokenUsers as $user) {
            $this->warn("  User ID: {$user->id}");
            $this->warn("    Email: {$user->email}");
            $this->warn("    Type: {$user->userable_type}");
            $this->warn("    Entity ID: {$user->userable_id}");

            // Check if the referenced entity exists
            if ($user->userable_type === 'App\Models\Company') {
                $exists = Company::find($user->userable_id);
                if (!$exists) {
                    $this->error("    ❌ Company ID {$user->userable_id} does not exist");

                    if (!$dryRun) {
                        $this->fixBrokenCompanyRelationship($user);
                    } else {
                        $this->info("    🔧 Would create missing Company or clear relationship");
                    }
                }
            } elseif ($user->userable_type === 'App\Models\Operator') {
                $exists = Operator::find($user->userable_id);
                if (!$exists) {
                    $this->error("    ❌ Operator ID {$user->userable_id} does not exist");

                    if (!$dryRun) {
                        $this->fixBrokenOperatorRelationship($user);
                    } else {
                        $this->info("    🔧 Would create missing Operator or clear relationship");
                    }
                }
            }

            $this->newLine();
        }
    }

    private function findOrphanedEntities($dryRun)
    {
        $this->info('2. Finding orphaned entities...');

        // Find companies without users
        $orphanedCompanies = Company::whereDoesntHave('user')->get();

        // Find operators without users
        $orphanedOperators = Operator::whereDoesntHave('user')->get();

        if ($orphanedCompanies->isEmpty() && $orphanedOperators->isEmpty()) {
            $this->info('  ✅ No orphaned entities found');
            return;
        }

        if ($orphanedCompanies->isNotEmpty()) {
            $this->warn("  ⚠️ Found {$orphanedCompanies->count()} companies without users");
            foreach ($orphanedCompanies as $company) {
                $this->warn("    Company ID: {$company->id} - {$company->business_name}");

                if (!$dryRun) {
                    $this->createUserForCompany($company);
                } else {
                    $this->info("    🔧 Would create user for this company");
                }
            }
        }

        if ($orphanedOperators->isNotEmpty()) {
            $this->warn("  ⚠️ Found {$orphanedOperators->count()} operators without users");
            foreach ($orphanedOperators as $operator) {
                $this->warn("    Operator ID: {$operator->id} - {$operator->full_name}");

                if (!$dryRun) {
                    $this->createUserForOperator($operator);
                } else {
                    $this->info("    🔧 Would create user for this operator");
                }
            }
        }
    }

    private function fixBrokenCompanyRelationship($user)
    {
        $choice = $this->choice(
            "Fix broken Company relationship for user {$user->email}:",
            [
                'Create missing Company',
                'Clear relationship (set to null)',
                                'Delete user',
                                'Skip'
            ],
            3 // Default to skip
        );

        switch ($choice) {
            case 'Create missing Company':
                $company = Company::create([
                    'business_name' => "Company for {$user->name}",
                    'commercial_name' => "Company {$user->userable_id}",
                    'tax_id' => '99' . str_pad($user->userable_id, 9, '0', STR_PAD_LEFT),
                                           'country' => 'AR',
                                           'active' => true,
                                           'created_date' => now(),
                                           'certificate_expires_at' => now()->addYear(),
                ]);

                $user->update(['userable_id' => $company->id]);
                $this->info("    ✅ Created Company ID {$company->id}");
                break;

            case 'Clear relationship (set to null)':
                $user->update(['userable_type' => null, 'userable_id' => null]);
                $this->info("    ✅ Cleared relationship");
                break;

            case 'Delete user':
                $user->delete();
                $this->info("    ✅ Deleted user");
                break;

            default:
                $this->info("    ⏭️ Skipped");
                break;
        }
    }

    private function fixBrokenOperatorRelationship($user)
    {
        $choice = $this->choice(
            "Fix broken Operator relationship for user {$user->email}:",
            [
                'Create missing Operator',
                'Clear relationship (set to null)',
                                'Delete user',
                                'Skip'
            ],
            3 // Default to skip
        );

        switch ($choice) {
            case 'Create missing Operator':
                $operator = Operator::create([
                    'first_name' => 'Missing',
                    'last_name' => 'Operator',
                    'type' => 'external',
                    'active' => true,
                    'created_date' => now(),
                ]);

                $user->update(['userable_id' => $operator->id]);
                $this->info("    ✅ Created Operator ID {$operator->id}");
                break;

            case 'Clear relationship (set to null)':
                $user->update(['userable_type' => null, 'userable_id' => null]);
                $this->info("    ✅ Cleared relationship");
                break;

            case 'Delete user':
                $user->delete();
                $this->info("    ✅ Deleted user");
                break;

            default:
                $this->info("    ⏭️ Skipped");
                break;
        }
    }

    private function createUserForCompany($company)
    {
        $user = User::create([
            'name' => $company->business_name,
            'email' => 'admin@' . strtolower(str_replace(' ', '', $company->business_name)) . '.com',
                             'password' => bcrypt('password'),
                             'email_verified_at' => now(),
                             'userable_type' => 'App\Models\Company',
                             'userable_id' => $company->id,
                             'active' => true,
        ]);

        $user->assignRole('company-admin');
        $this->info("    ✅ Created user for Company: {$user->email}");
    }

    private function createUserForOperator($operator)
    {
        $user = User::create([
            'name' => $operator->full_name,
            'email' => strtolower($operator->first_name) . '@' .
            ($operator->company ? 'company.com' : 'internal.com'),
                             'password' => bcrypt('password'),
                             'email_verified_at' => now(),
                             'userable_type' => 'App\Models\Operator',
                             'userable_id' => $operator->id,
                             'active' => true,
        ]);

        $role = $operator->type === 'internal' ? 'internal-operator' : 'external-operator';
        $user->assignRole($role);
        $this->info("    ✅ Created user for Operator: {$user->email}");
    }

    private function displaySummary()
    {
        $this->newLine();
        $this->info('=== SUMMARY ===');

        $total = User::count();
        $withRelations = User::whereNotNull('userable_type')->count();
        $withoutRelations = User::whereNull('userable_type')->count();
        $brokenRelations = User::whereNotNull('userable_type')
        ->whereNotNull('userable_id')
        ->whereDoesntHave('userable')
        ->count();

        $companies = Company::count();
        $operators = Operator::count();
        $companiesWithUsers = Company::whereHas('user')->count();
        $operatorsWithUsers = Operator::whereHas('user')->count();

        $this->info("Total Users: {$total}");
        $this->info("Users with relationships: {$withRelations}");
        $this->info("Users without relationships: {$withoutRelations}");
        $this->info("Broken relationships: {$brokenRelations}");
        $this->newLine();
        $this->info("Total Companies: {$companies}");
        $this->info("Companies with users: {$companiesWithUsers}");
        $this->info("Total Operators: {$operators}");
        $this->info("Operators with users: {$operatorsWithUsers}");

        if ($brokenRelations === 0) {
            $this->info('✅ All relationships are now valid!');
        } else {
            $this->error("❌ Still {$brokenRelations} broken relationships");
        }
    }
}
