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
        // Schema::create('businesses', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        //     $table->string('business_operating_name')->nullable();
        //     $table->string('business_website')->nullable();
        //     $table->string('business_legal_name')->nullable();
        //     $table->string('incorporation_country')->nullable();
        //     $table->string('business_operation_address')->nullable();
        //     $table->string('entity_type')->nullable();
        //     $table->string('business_registration_number')->nullable();
        //     $table->string('business_tax_id')->nullable();
        //     $table->string('business_industry')->nullable();
        //     $table->string('business_sub_industry')->nullable();
        //     $table->longText('business_description')->nullable();
        //     $table->text('account_purpose')->nullable();
        //     $table->string('plan_of_use')->nullable();
        //     $table->boolean('is_pep_owner')->nullable();
        //     $table->boolean('is_ofac_sanctioned')->nullable();
        //     $table->bigInteger('shareholder_count')->nullable();
        //     $table->json('shareholders')->nullable();
        //     $table->bigInteger('directors_count')->nullable();
        //     $table->json('directors')->nullable();
        //     $table->json('documents')->nullable();
        //     $table->string('estimated_monthly_transactions')->nullable();
        //     $table->string('estimated_monthly_payments')->nullable();
        //     $table->string('use_case')->nullable();
        //     $table->boolean('is_self_use')->default(false);
        //     $table->timestamp('terms_agreed_date')->useCurrent();
        //     $table->json('other_datas')->nullable();
        //     $table->timestamp('created_at')->useCurrent();
        //     $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        //     $table->softDeletes();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
