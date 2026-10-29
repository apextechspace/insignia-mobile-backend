<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->command) {
            throw new RuntimeException('Run this seeder from the terminal to enter the owner account details.');
        }

        $name = trim((string) $this->command->ask('Owner name', 'Insignia Owner'));
        $email = strtolower(trim((string) $this->command->ask('Owner login email')));
        $identity = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->validate();

        $existing = User::where('email', $identity['email'])->first();
        if ($existing) {
            if (!$existing->isIplAdmin()) {
                throw new RuntimeException('That email belongs to an existing non-owner account. Choose another email; the account was not changed.');
            }
            $this->command->info('Owner account already exists. Its password and details were left unchanged.');
            return;
        }

        $password = $this->command->secret('Owner password');
        $confirmation = $this->command->secret('Confirm owner password');
        Validator::make(['password' => $password, 'password_confirmation' => $confirmation], [
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ])->validate();

        $owner = User::firstOrCreate(['email' => $identity['email']], [
            'name' => $identity['name'], 'role' => User::ROLE_IPL_ADMIN,
            'password' => Hash::make($password),
        ]);
        $this->command->info($owner->wasRecentlyCreated
            ? 'Owner account created. You can sign in at /admin/login.'
            : 'An account was created concurrently. No existing account details were changed.');
        $this->command->info('No demo accounts, sample bookings, financial records or catalog changes were added.');
    }
}
