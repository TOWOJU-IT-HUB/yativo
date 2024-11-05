<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payins = PayinMethods::all();
        $payouts = PayoutMethods::all();
    
        foreach ($payins as $payin) {
            ExchangeRate::firstOrCreate(
                [
                    'gateway_id' => $payin->id,
                    'rate_type' => 'payin',
                ],
                [
                    'float_percentage' => 1,
                ]
            );
        }
    
        foreach ($payouts as $payout) {
            ExchangeRate::firstOrCreate(
                [
                    'gateway_id' => $payout->id,
                    'rate_type' => 'payout',
                ],
                [
                    'float_percentage' => 1,
                ]
            );
        }
    }
}    