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
        Schema::create('binance_pays', function (Blueprint $table) {
            $table->id();
            $table->string('deposit_id');
            $table->string('gateway_id');
            $table->enum('trx_type', ['deposit', 'send_money', 'withdraw'])->default('deposit');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('binance_pays');
    }
};
