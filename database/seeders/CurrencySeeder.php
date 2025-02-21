<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Currencies\app\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'wallet' => 'COP',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'Colombian Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Colombian Peso',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/cop.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'CO'
            ],
            [
                'wallet' => 'MXN',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'Mexican Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Mexican Peso',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/mxn.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'MX'
            ],
            [
                'wallet' => 'ARS',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'Argentine Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Argentine Peso',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/ars.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'AR'
            ],
            [
                'wallet' => 'PEN',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'S/',
                'currency_name' => 'Peruvian Sol',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Peruvian Sol',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/pen.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'PE'
            ],
            [
                'wallet' => 'CLP',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'Chilean Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Chilean Peso',
                'decimal_places' => '0',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/clp.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'CL'
            ],
            [
                'wallet' => 'BRL',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'R$',
                'currency_name' => 'Brazilian Real',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Brazilian Real',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/brl.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'BR'
            ],
            [
                'wallet' => 'EUR',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '€',
                'currency_name' => 'Euro',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Euro',
                'decimal_places' => '2',
                'is_active' => false,
                'logo_url' => 'https://cdn.yativo.com/eur.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'EU'
            ],
            [
                'wallet' => 'USD',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'US Dollar',
                'balance_type' => 'fiat',
                'currency_full_name' => 'United States Dollar',
                'decimal_places' => '2',
                'is_active' => true,
                'logo_url' => 'https://cdn.yativo.com/usd.svg',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'US'
            ],
        ];

        Currency::all()->each(function ($currency) {
            $currency->delete();
        });

        if (!Schema::hasColumn('currencies', 'is_active')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->boolean('is_active')->nullable();
            });
        }

        Currency::insertOrIgnore($currencies);
    }
}
