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
        Schema::create('beneficiaries_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('payout_methods');
            $table->foreignId('beneficiary_id')->constrained('beneficiaries');
            $table->string('nickname')->nullable(false);
            $table->string('currency');
            $table->longText('address')->nullable();
            $table->longText('payment_data');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
