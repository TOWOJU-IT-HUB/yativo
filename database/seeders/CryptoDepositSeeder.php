<?php

namespace Database\Seeders;

use App\Models\CryptoDeposit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CryptoDepositSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            [
                "id" => 1,
                "user_id" => 1,
                "currency" => "USDT.BEP20",
                "amount" => "1.00000000",
                "address" => "0x6644971cbadcb8db18c80f4cb5eac4faf0cd9839",
                "transaction_id" => "0xedc60872a32252da242eea3d1c7c5f22cef73367f7a5b78ec6ce1af086fd67ec",
                "status" => "success",
                "created_at" => "2024-08-28T13:36:36.000000Z",
                "customer" => null
            ],
            [
                "id" => 30,
                "user_id" => 1,
                "currency" => "USDT.BEP20",
                "amount" => "1.00000000",
                "address" => "0x6644971cbadcb8db18c80f4cb5eac4faf0cd9839",
                "transaction_id" => "0xedc60872a32252da242eea3d1c7c5f22cef73367f7a5b78ec6ce1af086fd67ec2",
                "status" => "success",
                "created_at" => "2024-08-28T13:45:51.000000Z",
                "customer" => null
            ],
            [
                "id" => 79,
                "user_id" => 7,
                "currency" => "USDT.BEP20",
                "amount" => "3.00000000",
                "address" => "0x6644971cbadcb8db18c80f4cb5eac4faf0cd9839",
                "transaction_id" => "0x21f4a797d186c19c85d28fb574f45864f1e9078638a2d2db327a7d1e5fbd95be",
                "status" => "success",
                "created_at" => "2024-08-28T13:47:10.000000Z",
                "customer" => null
            ]
        ];

        // Define available customer IDs
        $customerIds = [
            '9c12e651-5248-4d9a-ad49-05ccfd9a2d3c',
        ];

        // Insert data into the CryptoDeposit model
        foreach ($data as &$entry) {
            // Add a random customer_id to each entry
            $entry['customer_id'] = $customerIds[array_rand($customerIds)];
            CryptoDeposit::truncate();
            // Insert each entry, using insertOrIgnore to avoid duplication
            CryptoDeposit::insertOrIgnore([
                'user_id' => 2,
                'currency' => $entry['currency'],
                'amount' => $entry['amount'],
                'address' => $entry['address'],
                'transaction_id' => $entry['transaction_id'],
                'status' => $entry['status'],
                'created_at' => $entry['created_at'],
                'customer_id' => $entry['customer_id']
            ]);
        }

    }
}
