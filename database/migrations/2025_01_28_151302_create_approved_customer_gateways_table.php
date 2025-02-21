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
        Schema::dropIfExists('approved_customer_gateways');
        Schema::create('approved_customer_gateways', function (Blueprint $table) {
            $table->id();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->string('gateway_id');
            $table->enum('gateway_type', ['payin', 'payout']);
            $table->enum('status', ['approved', 'pending', 'rejected', 'disabled']);
            $table->timestamps();
            $table->softDeletes();
        });        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approved_customer_gateways');
    }
};
