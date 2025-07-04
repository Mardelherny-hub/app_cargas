<?php
// app/Console/Commands/QuickFixUsersCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;
use App\Models\Operator;

class QuickFixUsersCommand extends Command
{
    protected $signature = 'users:quick-fix';
    protected $description = 'Quick fix for user relationships';

    public function handle()
    {
        $this->info('=== QUICK FIX FOR USER RELATIONSHIPS ===');
        $this->newLine();

        // Check current state
        $this->checkCurrentState();

        // Fix empty string relationships
        $this->fixEmptyStringRelationships();

        // Check again
        $this->checkCurrentState();

        $this->info('✅ Quick fix completed!');
    }

    private function checkCurrentState()
    {
        $this->info('Current state:');

        $total = User::count();
        $withCompany = User::where('userable_type', 'App\Models\Company')->count();
        $withOperator = User::where('userable_type', 'App\Models\Operator')->count();
        $withNull = User::whereNull('userable_type')->count();
        $withEmpty = User::where('userable_type', '')->count();

        $this->info("  Total users: {$total}");
        $this->info("  Users → Company: {$withCompany}");
        $this->info("  Users → Operator: {$withOperator}");
        $this->info("  Users with NULL type: {$withNull}");
        $this->info("  Users with empty string type: {$withEmpty}");

        // Check for truly broken relationships
        $brokenRelations = User::whereNotNull('userable_type')
        ->whereNotNull('userable_id')
        ->where('userable_type', '!=', '')
        ->where('userable_id', '!=', '')
        ->whereDoesntHave('userable')
        ->count();

        if ($brokenRelations > 0) {
            $this->error("  ❌ Broken relationships: {$brokenRelations}");
        } else {
            $this->info("  ✅ No broken relationships");
        }

        $this->newLine();
    }

    private function fixEmptyStringRelationships()
    {
        $this->info('Fixing empty string relationships...');

        // Find users with empty string userable_type
        $usersWithEmptyType = User::where('userable_type', '')->get();

        if ($usersWithEmptyType->isNotEmpty()) {
            foreach ($usersWithEmptyType as $user) {
                $this->warn("  Fixing user: {$user->email}");
                $user->update([
                    'userable_type' => null,
                    'userable_id' => null
                ]);
                $this->info("    ✅ Set to NULL");
            }
        }

        // Find users with empty string userable_id
        $usersWithEmptyId = User::where('userable_id', '')->get();

        if ($usersWithEmptyId->isNotEmpty()) {
            foreach ($usersWithEmptyId as $user) {
                $this->warn("  Fixing user: {$user->email}");
                $user->update([
                    'userable_type' => null,
                    'userable_id' => null
                ]);
                $this->info("    ✅ Set to NULL");
            }
        }

        $this->info('Empty string relationships fixed.');
        $this->newLine();
    }
}
