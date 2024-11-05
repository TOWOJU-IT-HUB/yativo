<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
                'id' => 1,
                'wallet' => 'USD',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '$',
                'currency_name' => 'United States Dollar',
                'balance_type' => 'fiat',
                'currency_full_name' => 'United States Dollar',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/us.svg', 
                'created_at' => '2024-05-20T18:33:54.000000Z',
                'updated_at' => '2024-05-20T18:33:54.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 1,
                'currency_country' => 'US'
            ],
            [
                'id' => 2,
                'wallet' => 'EUR',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '€',
                'currency_name' => 'Euro',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Euro',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/eu.svg', 
                'created_at' => '2024-05-20T18:33:55.000000Z',
                'updated_at' => '2024-06-28T15:43:01.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'EU'
            ],
            [
                'id' => 3,
                'wallet' => 'GBP',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => '£',
                'currency_name' => 'British Pound',
                'balance_type' => 'fiat',
                'currency_full_name' => 'British Pound',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/gb.svg', 
                'created_at' => '2024-05-20T18:33:56.000000Z',
                'updated_at' => '2024-06-28T15:43:35.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'GB'
            ],
            [
                'id' => 4,
                'wallet' => 'CLP',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'CLP$',
                'currency_name' => 'Chilean Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Chilean Peso',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/cl.svg',
                'created_at' => '2024-05-20T18:33:56.000000Z',
                'updated_at' => '2024-06-28T15:39:59.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 1,
                'currency_country' => 'CL'
            ],
            [
                'id' => 5,
                'wallet' => 'PEN',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'PEN',
                'currency_name' => 'Peruvian Sol',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Peruvian Sol',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/pe.svg',
                'created_at' => '2024-05-20T18:33:57.000000Z',
                'updated_at' => '2024-05-20T18:33:57.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 0,
                'currency_country' => 'PE'
            ],
            [
                'id' => 6,
                'wallet' => 'ARS',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'ARS$',
                'currency_name' => 'Argentine Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Argentine Peso',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/ar.svg',
                'created_at' => '2024-05-20T18:33:58.000000Z',
                'updated_at' => '2024-06-28T15:44:19.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 1,
                'currency_country' => 'AR'
            ],
            [
                'id' => 7,
                'wallet' => 'MXN',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'MX$',
                'currency_name' => 'Mexican Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Mexican Peso',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/mx.svg', 
                'created_at' => '2024-05-20T18:33:59.000000Z',
                'updated_at' => '2024-06-28T15:45:06.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 1,
                'currency_country' => 'MX'
            ],
            [
                'id' => 8,
                'wallet' => 'COP',
                'main_balance' => 0,
                'ledger_balance' => 0,
                'currency_icon' => 'COL$',
                'currency_name' => 'Colombian Peso',
                'balance_type' => 'fiat',
                'currency_full_name' => 'Colombian Peso',
                'decimal_places' => '2',
                'logo_url' => 'https://cdn.yativo.com/co.svg',
                'created_at' => '2024-05-20T18:33:59.000000Z',
                'updated_at' => '2024-05-20T18:33:59.000000Z',
                'deleted_at' => null,
                'can_hold_balance' => 1,
                'currency_country' => 'CO'
            ]
        ];

        Currency::insertOrIgnore($currencies);
    }
}
