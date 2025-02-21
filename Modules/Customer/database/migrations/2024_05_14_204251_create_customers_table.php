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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId("user_id")->constrained('users')->onDelete("cascade");
            $table->string("customer_id");
            $table->string("customer_name");
            $table->string("customer_email");
            $table->string("customer_phone");
            $table->string("customer_country")->nullable();
            // must be encrypted and decrypted when needed
            
            $table->json("customer_address")->nullable();
            $table->string("customer_idType")->nullable();
            $table->string("customer_idNumber")->nullable();
            $table->string("customer_idCountry")->nullable();
            $table->string("customer_idExpiration")->nullable();
            $table->string("customer_idFront")->nullable();
            $table->string("customer_idBack")->nullable();

            // customer bridge kyc info
            $table->string("bridge_customer_id")->nullable();
            $table->longText("customer_kyc_link")->nullable();
            $table->string("customer_kyc_link_id")->nullable();

            $table->boolean('can_create_vc')->comment('can create virtual cards')->default(false);
            $table->boolean('can_create_va')->comment('can create virtual account')->default(false);
            $table->enum("customer_status", ['active', 'suspended'])->default('active');
            $table->enum("customer_type", ['individual', 'business'])->default('individual');
            $table->enum("customer_kyc_status", ['not_started', 'pending', 'incomplete', 'awaiting_ubo', 'manual_review', 'under_review', 'approved', 'rejected',])->default('not_started');
            $table->string("customer_kyc_reject_reason")->nullable();
            $table->string("admin_kyc_reject_reason")->nullable();
            $table->json("json_data")->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('customers');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
