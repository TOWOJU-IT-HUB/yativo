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
        Schema::table('transaction_records', function (Blueprint $table) {
            $table->string('swap_from_currency')->nullable();
            $table->string('swap_to_currency')->nullable();
            $table->string('transaction_currency')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_records', function (Blueprint $table) {
            $table->dropColumn('swap_from_currency');
            $table->dropColumn('swap_to_currency');
            $table->dropColumn('transaction_currency');
        });
    }
};
