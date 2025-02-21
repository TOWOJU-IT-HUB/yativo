<?php

namespace Database\Seeders;

use App\Models\BeneficiaryFoems;
use App\Models\payoutMethods;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TempSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // run query to get all gateways for a country that uses bank_transfer
        $payins = payoutMethods::where(['gateway' => 'transfi', 'payment_mode' => 'local_wallet'])->get();

        foreach ($payins as $payin) {
            $record = [
                'gateway_id' => $payin->id,
                'currency' => $payin->currency,
                'form_data' => [
                    'payment_data' => [
                        [
                            'key' => 'accountNumber',
                            'name' => 'Account Number',
                            'type' => 'text',
                            'value' => '',
                            'required' => true,
                        ],
                        [
                            'key' => 'account_type',
                            'name' => 'Account Type',
                            'type' => 'select',
                            'value' => '',
                            'options' => [
                                [
                                    'label' => 'US',
                                    'value' => 'us',
                                ]
                            ],
                            'required' => true,
                        ]
                    ],
                ],
            ];

            BeneficiaryFoems::create($record);
        }
    }
}
