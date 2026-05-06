<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Promote an existing user to super-admin, or create a new super-admin account.
 *
 *   php artisan make:super-admin                      # interactive
 *   php artisan make:super-admin you@example.com      # promote existing or create
 *   php artisan make:super-admin you@example.com --revoke   # demote
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'make:super-admin
                            {email? : Email of the user to promote (or create)}
                            {--name= : Name to use if creating a new user}
                            {--password= : Password to set (auto-generated if omitted)}
                            {--revoke : Demote this user from super-admin instead of promoting}';

    protected $description = 'Create or promote a super-admin user. Safe to run repeatedly.';

    public function handle(): int
    {
        $email = $this->argument('email') ?: $this->ask('Email');
        if (! $email) {
            $this->error('Email is required.');
            return self::FAILURE;
        }
        $email = strtolower(trim($email));

        $user = User::where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user) {
                $this->error("No user with email {$email}.");
                return self::FAILURE;
            }
            $user->forceFill(['is_super_admin' => false])->save();
            $this->info("Revoked super-admin from {$user->name} ({$email}).");
            return self::SUCCESS;
        }

        if ($user) {
            if ($user->is_super_admin) {
                $this->info("{$user->name} ({$email}) is already a super-admin.");
                return self::SUCCESS;
            }
            $user->forceFill(['is_super_admin' => true])->save();
            $this->info("Promoted {$user->name} ({$email}) to super-admin.");
            return self::SUCCESS;
        }

        // No user — create one. Need a name, and a password (or auto-generate).
        $name     = $this->option('name')     ?: $this->ask('Name', 'Super Admin');
        $password = $this->option('password') ?: null;
        $generated = false;
        if (! $password) {
            $password  = Str::random(14);
            $generated = true;
        }

        $user = new User([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);
        // is_super_admin + email_verified_at are guarded — set explicitly.
        $user->email_verified_at = now();
        $user->is_super_admin    = true;
        $user->save();

        $this->info("Created super-admin {$user->name} ({$email}).");
        if ($generated) {
            $this->newLine();
            $this->warn('Temporary password (store it now — it will not be shown again):');
            $this->line('  ' . $password);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
