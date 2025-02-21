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
        Schema::create('bridges', function (Blueprint $table) {
            $table->id();
             // General Information
             $table->string('type')->default('individual'); // Can be 'individual' or 'business'
             $table->string('customer_id')->constrained('customers');
             $table->string('first_name')->nullable();
             $table->string('middle_name')->nullable();
             $table->string('last_name')->nullable();
             $table->string('transliterated_first_name')->nullable();
             $table->string('transliterated_middle_name')->nullable();
             $table->string('transliterated_last_name')->nullable();
             $table->string('email')->unique();
             $table->string('phone')->nullable();
             $table->string('street_line_1');
             $table->string('street_line_2')->nullable();
             $table->string('city');
             $table->string('state');
             $table->string('postal_code');
             $table->string('country');
             $table->date('birth_date')->nullable();
             $table->string('tax_identification_number')->nullable();
             $table->string('signed_agreement_id')->nullable();
             $table->string('gov_id_country')->nullable();
             $table->string('gov_id_image_front')->nullable();
             $table->string('gov_id_image_back')->nullable();
             $table->string('proof_of_address_document')->nullable();
             
             // Business-Specific Fields
             $table->enum('business_type', ['corporation', 'llc', 'cooperative', 'other', 'partnership', 'trust', 'sole_prop'])->default('other'); // e.g., cooperative
             $table->boolean('is_dao')->default(false); // Decentralized Autonomous Organization
             $table->boolean('transmits_customer_funds')->default(false);
 
             // SOF EU Questionnaire
             $table->boolean('acting_as_intermediary')->default(false);
             $table->string('employment_status')->nullable();
             $table->string('expected_monthly_payments')->nullable();
             $table->string('primary_purpose')->nullable();
             $table->string('source_of_funds')->nullable();
             $table->string('estimated_annual_revenue_usd')->nullable();
             $table->boolean('operates_in_prohibited_countries')->default(false);
             $table->boolean('transmits_customer_funds_questionnaire')->default(false);
 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bridges');
    }
};
