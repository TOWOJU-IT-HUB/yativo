<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            TempSeeder::class,
            // THBSeeder::class,
            // VNDSeeder::class,
            // MYRSeeder::class,
            // IDRSeeder::class,
            // CountryStateCityTableSeeder::class,
            // CurrencyListSeeder::class,
            // PayinMethodsSeeder::class,
            // PayoutMethodsSeeder::class,
            // PlanSeeder::class,
            // CurrencySeeder::class,
            // ExchangeRateSeeder::class,
            // CryptoDepositSeeder::class
            // AdminSeeder::class,
        ]);
    }
}
