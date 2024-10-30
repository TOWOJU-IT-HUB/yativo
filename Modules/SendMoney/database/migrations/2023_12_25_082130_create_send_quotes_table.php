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
        Schema::create('send_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('beneficiary_id')->constrained('beneficiaries');
            $table->string('action')->nullable();
            $table->string('send_amount')->nullable();
            $table->string('receive_amount')->nullable();
            $table->string('send_gateway')->nullable();
            $table->string('send_currency')->nullable();
            $table->string('receive_gateway')->nullable();
            $table->string('receive_currency')->nullable();
            $table->string('transfer_purpose')->nullable();
            $table->string('rate')->nullable();
            $table->string('total_amount')->nullable();
            $table->string('fee')->nullable();
            $table->json('charges')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_quotes');
    }
};
