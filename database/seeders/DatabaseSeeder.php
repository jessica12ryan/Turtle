<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin Landlord',
            'email' => 'admin@turtleapp.com',
            'password' => bcrypt('password'),
            'role' => 'landlord',
        ]);

        $company = Company::create([
            'name' => 'Turtle Properties Inc.',
            'address' => '123 Main Street',
            'city' => 'Toronto',
            'province' => 'Ontario',
            'postal_code' => 'M5A 1A1',
            'phone' => '555-0100',
        ]);

        $admin->companies()->attach($company);

        $manager = User::create([
            'name' => 'Jane Manager',
            'email' => 'manager@turtleapp.com',
            'password' => bcrypt('password'),
            'role' => 'property_manager',
        ]);
        $manager->companies()->attach($company);
    }
}
