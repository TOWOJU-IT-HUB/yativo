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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId("user_id")->constrained('users')->onDelete("cascade");
            $table->string("customer_id");
            $table->string("customer_name");
            $table->string("customer_email");
            $table->string("customer_phone");
            $table->string("customer_country");
            $table->json("customer_address")->nullable();
            $table->string("customer_idType")->nullable();
            $table->string("customer_idNumber")->nullable();
            $table->string("customer_idCountry")->nullable();
            $table->string("customer_idExpiration")->nullable();
            $table->string("customer_idFront")->nullable();
            $table->string("customer_idBack")->nullable();
            $table->boolean('can_create_vc')->comment('can create virtual cards')->default(false);
            $table->boolean('can_create_va')->comment('can create virtual account')->default(false);
            $table->enum("customer_status", ['active', 'suspended', 'pending', 'failed_kyc'])->default('pending');
            $table->json("json_data")->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
