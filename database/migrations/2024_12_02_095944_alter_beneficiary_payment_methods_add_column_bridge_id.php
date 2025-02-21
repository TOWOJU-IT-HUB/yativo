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
        Schema::table('beneficiaries_payment_methods', function (Blueprint $table) {
            $table->string("bridge_id")->nullable();
            $table->string("bridge_customer_id")->nullable();
            $table->json("bridge_response")->nullable();
        });
    }

};
