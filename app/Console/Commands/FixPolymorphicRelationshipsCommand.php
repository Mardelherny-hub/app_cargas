<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class FixPolymorphicRelationshipsCommand extends Command
{
    protected $signature = 'users:fix-polymorphic {--dry-run : Show what would be fixed without making changes}';
    protected $description = 'Fix broken polymorphic relationships in users table (3 simplified roles)';

    public function handle()
    {
        $this->info('=== FIXING POLYMORPHIC RELATIONSHIPS ===');
        $this->info('✨ For 3 simplified roles system');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // 1. Check current state
        $this->checkCurrentState();

        // 2. Find and fix broken relationships
        $this->findBrokenRelationships($dryRun);

        // 3. Find orphaned entities
        $this->findOrphanedEntities($dryRun);

        // 4. Clean up empty/invalid relationships
        $this->cleanupInvalidRelationships($dryRun);

        // 5. Final verification
        $this->finalVerification();

        if ($dryRun) {
            $this->newLine();
            $this->info('💡 Run without --dry-run to apply the fixes');
        } else {
            $this->newLine();
            $this->info('✅ All fixes applied successfully!');
        }
    }

    private function checkCurrentState()
    {
        $this->info('📊 Current state analysis...');

        $total = User::count();
        $withCompany = User::where('userable_type', 'App\Models\Company')->count();
        $withOperator = User::where('userable_type', 'App\Models\Operator')->count();
        $withNull = User::whereNull('userable_type')->count();
        $withEmpty = User::where('userable_type', '')->count();

        $this->info("  📈 Total users: {$total}");
        $this->info("  🏢 Users → Company: {$withCompany}");
        $this->info("  👤 Users → Operator: {$withOperator}");
        $this->info("  ⚪ Users with NULL type: {$withNull}");
        $this->info("  ⚠️ Users with empty string type: {$withEmpty}");

        // Check for broken relationships
        $brokenRelations = User::whereNotNull('userable_type')
            ->whereNotNull('userable_id')
            ->where('userable_type', '!=', '')
            ->whereDoesntHave('userable')
            ->count();

        if ($brokenRelations > 0) {
            $this->error("  ❌ Broken relationships found: {$brokenRelations}");
        } else {
            $this->info("  ✅ No broken relationships detected");
        }

        $this->newLine();
    }

    private function findBrokenRelationships($dryRun)
    {
        $this->info('🔍 Finding broken user relationships...');

        $brokenUsers = User::whereNotNull('userable_type')
            ->whereNotNull('userable_id')
            ->where('userable_type', '!=', '')
            ->whereDoesntHave('userable')
            ->get();

        if ($brokenUsers->isEmpty()) {
            $this->info('  ✅ No broken relationships found');
            return;
        }

        $this->error("  ❌ Found {$brokenUsers->count()} broken relationships");
        $this->newLine();

        foreach ($brokenUsers as $user) {
            $this->warn("  🔧 Broken relationship detected:");
            $this->warn("    User: {$user->name} ({$user->email})");
            $this->warn("    ID: {$user->id}");
            $this->warn("    Type: {$user->userable_type}");
            $this->warn("    Entity ID: {$user->userable_id}");

            if ($user->userable_type === 'App\Models\Company') {
                $this->fixBrokenCompanyRelationship($user, $dryRun);
            } elseif ($user->userable_type === 'App\Models\Operator') {
                $this->fixBrokenOperatorRelationship($user, $dryRun);
            } else {
                $this->warn("    ⚠️ Unknown userable_type: {$user->userable_type}");
                if (!$dryRun) {
                    $user->update(['userable_type' => null, 'userable_id' => null]);
                    $this->info("    ✅ Cleared invalid relationship");
                }
            }

            $this->newLine();
        }
    }

    private function findOrphanedEntities($dryRun)
    {
        $this->info('🔍 Finding orphaned entities...');

        // Companies without users (only company-admin should have users)
        $companiesWithoutUsers = Company::whereDoesntHave('users')->count();
        $operatorsWithoutUsers = Operator::whereDoesntHave('user')->count();

        $this->info("  🏢 Companies without users: {$companiesWithoutUsers}");
        $this->info("  👤 Operators without users: {$operatorsWithoutUsers}");

        if ($companiesWithoutUsers > 0) {
            $this->warn("  ⚠️ Some companies don't have admin users (this might be intentional)");
        }

        if ($operatorsWithoutUsers > 0) {
            $this->warn("  ⚠️ Some operators don't have user accounts");

            if (!$dryRun) {
                $orphanedOperators = Operator::whereDoesntHave('user')->get();
                foreach ($orphanedOperators as $operator) {
                    $this->createUserForOperator($operator);
                }
            } else {
                $this->info("  🔧 Would create user accounts for orphaned operators");
            }
        }

        $this->newLine();
    }

    private function cleanupInvalidRelationships($dryRun)
    {
        $this->info('🧹 Cleaning up invalid relationships...');

        // Find users with empty string relationships
        $usersWithEmptyType = User::where('userable_type', '')->count();
        $usersWithEmptyId = User::where('userable_id', '')->count();

        if ($usersWithEmptyType > 0 || $usersWithEmptyId > 0) {
            $this->warn("  ⚠️ Found users with empty string relationships");
            $this->warn("    Empty userable_type: {$usersWithEmptyType}");
            $this->warn("    Empty userable_id: {$usersWithEmptyId}");

            if (!$dryRun) {
                User::where('userable_type', '')->update([
                    'userable_type' => null,
                    'userable_id' => null
                ]);

                User::where('userable_id', '')->update([
                    'userable_type' => null,
                    'userable_id' => null
                ]);

                $this->info("  ✅ Cleaned up empty string relationships");
            } else {
                $this->info("  🔧 Would clean up empty string relationships");
            }
        } else {
            $this->info("  ✅ No invalid relationships found");
        }

        $this->newLine();
    }

    private function fixBrokenCompanyRelationship($user, $dryRun)
    {
        $this->warn("    ❌ Company ID {$user->userable_id} does not exist");

        if ($dryRun) {
            $this->info("    🔧 Would fix this relationship");
            return;
        }

        // For 3 roles system, we'll clear the relationship since the company doesn't exist
        // The user should be re-assigned to an existing company manually
        $user->update(['userable_type' => null, 'userable_id' => null]);
        $this->info("    ✅ Cleared broken company relationship");
        $this->warn("    ⚠️ User {$user->email} needs to be manually assigned to a company");
    }

    private function fixBrokenOperatorRelationship($user, $dryRun)
    {
        $this->warn("    ❌ Operator ID {$user->userable_id} does not exist");

        if ($dryRun) {
            $this->info("    🔧 Would fix this relationship");
            return;
        }

        // For 3 roles system, we'll clear the relationship since the operator doesn't exist
        // The user should be re-assigned to an existing operator manually
        $user->update(['userable_type' => null, 'userable_id' => null]);
        $this->info("    ✅ Cleared broken operator relationship");
        $this->warn("    ⚠️ User {$user->email} needs to be manually assigned to an operator");
    }

    private function createUserForOperator($operator)
    {
        // Generate a unique email for the operator
        $baseEmail = strtolower($operator->first_name . '.' . $operator->last_name);
        $domain = $operator->company_id ? '@company.com' : '@internal.com';
        $email = $baseEmail . $domain;

        // Check if email already exists
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = $baseEmail . $counter . $domain;
            $counter++;
        }

        $user = User::create([
            'name' => $operator->first_name . ' ' . $operator->last_name,
            'email' => $email,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'userable_type' => 'App\Models\Operator',
            'userable_id' => $operator->id,
            'active' => true,
        ]);

        // Assign 'user' role (3 roles system)
        $user->assignRole('user');
        $this->info("    ✅ Created user for Operator: {$user->email}");
    }

    private function finalVerification()
    {
        $this->info('🔍 Final verification...');

        $brokenRelations = User::whereNotNull('userable_type')
            ->whereNotNull('userable_id')
            ->whereDoesntHave('userable')
            ->count();

        $total = User::count();
        $withRelations = User::whereNotNull('userable_type')->count();
        $withoutRelations = User::whereNull('userable_type')->count();

        $this->info("  📊 Final statistics:");
        $this->info("    Total users: {$total}");
        $this->info("    Users with relationships: {$withRelations}");
        $this->info("    Users without relationships: {$withoutRelations}");
        $this->info("    Broken relationships: {$brokenRelations}");

        if ($brokenRelations === 0) {
            $this->info("  ✅ All relationships are now valid!");
        } else {
            $this->error("  ❌ Still {$brokenRelations} broken relationships");
        }

        // Role distribution
        $this->info("  📋 Role distribution:");
        $superAdmins = User::role('super-admin')->count();
        $companyAdmins = User::role('company-admin')->count();
        $users = User::role('user')->count();

        $this->info("    super-admin: {$superAdmins}");
        $this->info("    company-admin: {$companyAdmins}");
        $this->info("    user: {$users}");

        $this->newLine();
    }
}
