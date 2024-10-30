d<?php

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
        Schema::create('customer_virtual_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId("business_id")->constrained();
            $table->uuid("customer_id");
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->string('customer_card_id')->nullable();
            $table->string('card_number')->nullable();
            $table->string('expiry_date')->nullable();
            $table->string('cvv')->nullable();
            $table->string('card_id')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_virtual_cards');
    }
};
