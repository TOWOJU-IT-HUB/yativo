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
        Schema::create('dojah_verifications', function (Blueprint $table) {
            $table->uuid('id');
            $table->json('user_request');
            $table->string('kyc_status')->default('started');
            $table->string('dojah_kyc_url')->nullable();
            $table->json('dojah_response');
            $table->json('verification_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dojah_verifications');
    }
};
