<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                "meta_key" => "site_name",
                "meta_value" => "YATIVO",
            ],
            [
                "meta_key" => "virtual_card_creation",
                "meta_value" => "5",
            ],
        ];
    }
}
