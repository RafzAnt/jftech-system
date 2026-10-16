<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keep the documented development administrator account in sync with
        // the credentials shown on the login screen/setup instructions.
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'full_name' => 'System Administrator',
                'email' => 'admin@jftech.com',
                'phone' => '09123456789',
                'role' => 'super_admin',
                'status' => 'active',
                'archived' => false,
            ],
        );
    }
}
