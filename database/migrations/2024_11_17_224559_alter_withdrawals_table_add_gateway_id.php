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
        Schema::table('withdraws', function (Blueprint $table) {
            $table->unsignedBigInteger('gateway_id')->nullable();
            $table->string('customer_id')->nullable();
            $table->foreign('gateway_id')->references('id')->on('payout_methods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('withdraws', function (Blueprint $table) {
            $table->dropForeign(['gateway_id']);
            $table->dropColumn('gateway_id');
            $table->dropColumn('customer_id');
        });
    }
};
