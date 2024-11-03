<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Creatydev\Plans\Models\PlanModel;
use Carbon\Carbon;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'id' => 1,
                'name' => 'Start',
                'description' => 'Free plan of all.',
                'price' => 0.00,
                'currency' => 'USD',
                'duration' => 30,
                'metadata' => [
                    'features' => [
                        "All supported currencies",
                        "Transaction Fees: 1.5% + IVA*",
                        "Unlimited transactions",
                        "Treasury Wallets"
                    ]
                ],
                'created_at' => Carbon::create(2024, 5, 31, 12, 39, 24),
                'updated_at' => Carbon::create(2024, 5, 31, 12, 39, 24),
            ],
            [
                'id' => 2,
                'name' => 'Scale',
                'description' => 'Business with more needs.',
                'price' => 99.00,
                'currency' => 'USD',
                'duration' => 30,
                'metadata' => [
                    'features' => [
                        "Everything in Start +:",
                        "Transaction Fees: 1% + IVA*",
                        "Banking account issuing",
                        "Wallets as a service",
                        "Dedicated Account Manager"
                    ]
                ],
                'created_at' => Carbon::create(2024, 5, 31, 12, 39, 25),
                'updated_at' => Carbon::create(2024, 5, 31, 12, 39, 25),
            ],
            [
                'id' => 3,
                'name' => 'Enterprise',
                'description' => 'The biggest plans of all.',
                'price' => 0.00,
                'currency' => 'USD',
                'duration' => 30,
                'metadata' => [
                    'features' => [
                        "Everything in Scale +:",
                        "Custom Fees",
                        "Card issuing",
                        "Cash/Card Payins",
                        "Whitelabel"
                    ]
                ],
                'created_at' => Carbon::create(2024, 5, 31, 17, 15, 31),
                'updated_at' => Carbon::create(2024, 5, 31, 17, 20, 7),
            ]
        ];

        foreach ($plans as $planData) {
            PlanModel::updateOrCreate(
                ['id' => $planData['id']],
                [
                    'name' => $planData['name'],
                    'description' => $planData['description'],
                    'price' => $planData['price'],
                    'currency' => $planData['currency'],
                    'duration' => $planData['duration'],
                    'metadata' => json_encode($planData['metadata']),
                    'created_at' => $planData['created_at'],
                    'updated_at' => $planData['updated_at'],
                ]
            );
        }
    }
}
