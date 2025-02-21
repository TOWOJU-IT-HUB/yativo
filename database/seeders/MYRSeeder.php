<?php

namespace Database\Seeders;

use App\Models\payoutMethods;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MYRSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * MYS
     */
    public function run(): void
    {
        $paymentMethods = [
            0 => [
                'name' => 'AFFIN Bank',
                'paymentCode' => 'my_afb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            1 => [
                'name' => 'Alliance Bank',
                'paymentCode' => 'my_alb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            2 => [
                'name' => 'Ambank berhad',
                'paymentCode' => 'my_arb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            3 => [
                'name' => 'Bank Islam Malaysia',
                'paymentCode' => 'my_bimb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            4 => [
                'name' => 'Bank Rakyat Malaysia',
                'paymentCode' => 'my_bkr',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            5 => [
                'name' => 'Bank Simpanan Nasional',
                'paymentCode' => 'my_bsn',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            6 => [
                'name' => 'CIMB Bank',
                'paymentCode' => 'my_cimb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            7 => [
                'name' => 'CITI Bank',
                'paymentCode' => 'my_citi',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            8 => [
                'name' => 'HSBC Bank Malaysia',
                'paymentCode' => 'my_hsbc',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            9 => [
                'name' => 'Hong Leong Bank',
                'paymentCode' => 'my_hlb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            10 => [
                'name' => 'Maybank Berhad',
                'paymentCode' => 'my_maybank',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            11 => [
                'name' => 'Public Bank',
                'paymentCode' => 'my_pbb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            12 => [
                'name' => 'RHB Bank',
                'paymentCode' => 'my_rhb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/rhb_bank.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            13 => [
                'name' => 'Standard Chartered Bank',
                'paymentCode' => 'my_scb',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
            14 => [
                'name' => 'United Overseas Bank Berhad',
                'paymentCode' => 'my_uob',
                'minAmount' => 50,
                'maxAmount' => 50000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 10,
                        'max' => 14,
                        'pattern' => '^\\d{10,14}$',
                    ],
                ],
            ],
        ];

        foreach ($paymentMethods as $k => $v) {
            payoutMethods::updateOrCreate(
                [
                    'method_name' => $v['name'],
                    'payment_method_code' => $v['paymentCode'],
                ],
                [
                    'method_name' => $v['name'],
                    'gateway' => 'transfi',
                    'country' => 'MYS',
                    'currency' => 'MYR',
                    'payment_method_code' => $v['paymentCode'],
                    'payment_mode' => $v['paymentType'],
                    'charges_type' => 'combined',
                    'fixed_charge' => '1',
                    'float_charge' => '2.3',
                    'estimated_delivery' => '0.5',
                    'pro_fixed_charge' => '0',
                    'pro_float_charge' => '2.3',
                    'minimum_withdrawal' => $v['minAmount'],
                    'maximum_withdrawal' => $v['maxAmount'],
                    'minimum_charge' => '1',
                    'maximum_charge' => '100',
                    'cutoff_hrs_start' => '1',
                    'cutoff_hrs_end' => '15',
                ]
            );
        }
    }
}
