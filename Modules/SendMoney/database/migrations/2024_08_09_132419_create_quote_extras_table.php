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
        Schema::create('quote_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id');
            $table->string('transfer_purpose')->nullable();
            $table->string('transfer_memo')->nullable();
            $table->string('attachment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_extras');
    }
};
