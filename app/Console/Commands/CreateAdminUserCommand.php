<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first administrator account.
 *
 * Hosted platforms (Vercel, Render, Fly) give no shell, so there is no way to
 * run `php artisan tinker` against the production database. This command is the
 * supported way to bootstrap a fresh install without ever shipping the demo
 * credentials from DatabaseSeeder (admin@barangay.local / password).
 *
 * Safe by design:
 *   - It refuses to overwrite an existing account unless --force is passed.
 *   - It refuses to create an admin while any admin already exists.
 *   - The password is either prompted (hidden, never echoed) or supplied via
 *     the ADMIN_PASSWORD environment variable, which must be removed afterwards.
 */
class CreateAdminUserCommand extends Command
{
    protected $signature = 'app:create-admin
                            {--name= : Full name of the administrator}
                            {--email= : Login email address}
                            {--password= : Password (prefer the hidden prompt or ADMIN_PASSWORD)}
                            {--force : Create the account even if an administrator already exists}';

    protected $description = 'Create an administrator account on a fresh database';

    public function handle(): int
    {
        // Env::get() reads $_ENV/$_SERVER directly, so bootstrap secrets keep
        // working when the config cache is warm (env() returns null there).
        $name = $this->option('name') ?: Env::get('ADMIN_NAME');
        $email = $this->option('email') ?: Env::get('ADMIN_EMAIL');
        $password = $this->option('password') ?: Env::get('ADMIN_PASSWORD');

        $name = $name ?: $this->ask('Administrator name');
        $email = $email ?: $this->ask('Administrator email');

        if (blank($password)) {
            $password = $this->secret('Password (min 12 characters with letters and numbers, hidden)');
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'max:72', Password::min(12)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existingAdmins = User::query()->where('user_type', User::ROLE_ADMIN)->count();

        if ($existingAdmins > 0 && ! $this->option('force')) {
            $this->error("An administrator already exists ({$existingAdmins} found). Use --force to add another.");

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! $this->option('force')) {
            $this->error("A user with {$email} already exists. Use --force to overwrite its password.");

            return self::FAILURE;
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => User::ROLE_ADMIN,
            'status' => 'approved',
            // The account is created by an operator out-of-band, so there is
            // nothing to verify by email.
            'email_verified_at' => now(),
            'approved_at' => now(),
        ];

        if ($existing) {
            $existing->forceFill($attributes)->save();
            $this->info("Updated administrator {$email}.");
        } else {
            (new User)->forceFill($attributes)->save();
            $this->info("Created administrator {$email}.");
        }

        $this->newLine();
        $this->warn('Delete ADMIN_PASSWORD from your environment variables now, if you set one.');
        $this->line('Sign in at /login, then change this password from your profile.');

        return self::SUCCESS;
    }
}