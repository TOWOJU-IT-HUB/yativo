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
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('decimal_places')->nullable()->after('currency_full_name')->default(2);
            $table->string('logo_url')->nullable()->after('decimal_places')->default('https://yativo.com/wp-content/uploads/2024/03/Yativo-42x43_090555.png');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('', function (Blueprint $table) {
            
        });
    }
};
