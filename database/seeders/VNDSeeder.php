<?php

namespace Database\Seeders;

use App\Models\payoutMethods;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VNDSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * MYS
     */
    public function run(): void
    {
        $paymentMethods = [
            0 => [
                'name' => 'Bangkok Bank',
                'paymentCode' => 'bangkok_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            1 => [
                'name' => 'CIMB Thai',
                'paymentCode' => 'cimb_thai',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            2 => [
                'name' => 'Government Housing Bank',
                'paymentCode' => 'government_housing_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            3 => [
                'name' => 'Government Savings Bank',
                'paymentCode' => 'government_savings_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            4 => [
                'name' => 'Hong Kong Shanghai Bank',
                'paymentCode' => 'hong_kong_shanghai_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            5 => [
                'name' => 'KTB Net Bank',
                'paymentCode' => 'ktb_net_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            6 => [
                'name' => 'KasiKorn Bank',
                'paymentCode' => 'kasi_korn_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            7 => [
                'name' => 'Krungsri Bank',
                'paymentCode' => 'krungsri_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            8 => [
                'name' => 'Land and Houses Bank',
                'paymentCode' => 'land_and_houses_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            9 => [
                'name' => 'Siam Commercial Bank Plc.',
                'paymentCode' => 'siam_commercial_bank',
                'minAmount' => 300,
                'maxAmount' => 2000000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            10 => [
                'name' => 'Standard Chartered Bank',
                'paymentCode' => 'standard_chartered_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            11 => [
                'name' => 'TMB Bank Public Company Limited',
                'paymentCode' => 'tmb_bank_public_company_limited',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
                    ],
                ],
            ],
            12 => [
                'name' => 'UOB Bank ',
                'paymentCode' => 'uob_bank',
                'minAmount' => 500,
                'maxAmount' => 500000,
                'logoUrl' => 'https://common-payment-methods-logo.s3.ap-southeast-1.amazonaws.com/bank_transfer.svg',
                'paymentType' => 'bank_transfer',
                'additionalDetails' => [
                    'accountNumber' => [
                        'type' => 'string',
                        'min' => 9,
                        'max' => 16,
                        'pattern' => '^\\d{9,16}$',
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
                    'country' => 'VNM',
                    'currency' => 'VND',
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
