<?php

namespace Database\Seeders;

use DB;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('admins')->insert([
            'name' => 'Emma',
            'email' => 'emma@yativo.com',
            'password' => Hash::make('Adedayo201@!'),
            'is_two_factor_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
