<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the single hub user (idempotent + safe to run on every deploy).
     * Credentials come from .env. The password is set only when the user is
     * first created, so re-seeding never clobbers a password you changed later.
     */
    public function run(): void
    {
        $user = User::firstOrNew(['email' => env('ADMIN_EMAIL', 'admin@example.com')]);

        $user->name = env('ADMIN_NAME', 'Admin');

        if (! $user->exists) {
            $user->password = Hash::make(env('ADMIN_PASSWORD', 'password'));
        }

        $user->save();
    }
}
