<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('wallet');
            $table->integer('main_balance')->default(0);
            $table->integer('ledger_balance')->default(0);
            $table->string('currency_icon');
            $table->string('currency_name');
            $table->enum('balance_type', ['fiat', 'crypto'])->default('fiat');
            $table->string('currency_full_name');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
        });
        
        // $currencies = [
        //     [
        //         "wallet" => "USD",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "$",
        //         "currency_name" =>  "United State Dollars",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "United State Dollars",
        //         "can_hold_balance" => true
        //     ],
        //     [
        //         "wallet" => "EUR",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "€",
        //         "currency_name" =>  "Euro",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Euro",
        //         "can_hold_balance" => true
        //     ],
        //     [
        //         "wallet" => "GBP",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "£",
        //         "currency_name" =>  "British pounds",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "British pounds",
        //         "can_hold_balance" => true
        //     ],
        //     [
        //         "wallet" => "CLP",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "CLP$",
        //         "currency_name" =>  "Chilean Pesos",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Chilean Pesos",
        //         "can_hold_balance" => false
        //     ],
        //     [
        //         "wallet" => "PEN",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "PEN",
        //         "currency_name" =>  "Peruvian sol",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Peruvian sol",
        //         "can_hold_balance" => false
        //     ],
        //     [
        //         "wallet" => "ARS",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "ARS$",
        //         "currency_name" =>  "Argentine Peso",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Argentine Peso",
        //         "can_hold_balance" => false
        //     ],
        //     [
        //         "wallet" => "MXN",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "MX$",
        //         "currency_name" =>  "Mexican Peso",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Mexican Peso",
        //         "can_hold_balance" => false
        //     ],
        //     [
        //         "wallet" => "COP",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "COL$",
        //         "currency_name" =>  "Colombian Peso",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Colombian Peso",
        //         "can_hold_balance" => false
        //     ],
        //     [
        //         "wallet" => "BRL",
        //         "main_balance" => 0,
        //         "ledger_balance" => 0,
        //         "currency_icon" =>  "R$",
        //         "currency_name" =>  "Brazilian Real",
        //         "balance_type" =>   "fiat",
        //         "currency_full_name" => "Brazilian Real",
        //         "can_hold_balance" => false
        //     ],
        // ];

        // // Insert data into the currencies table
        // foreach ($currencies as $currency) {
        //     DB::table("currencies")->insert($currency);
        // }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
