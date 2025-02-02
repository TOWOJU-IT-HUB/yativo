<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('admins')->updateOrInsert([
            'name' => 'Emma',
            'email' => 'emma@yativo.com',
            'password' => Hash::make('jhondoe@smith10!'),
            'is_two_factor_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
