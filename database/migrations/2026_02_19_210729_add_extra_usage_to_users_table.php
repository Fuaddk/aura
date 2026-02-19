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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('extra_usage_enabled')->default(false)->after('wallet_balance');
            $table->boolean('auto_refill_enabled')->default(false)->after('extra_usage_enabled');
            $table->unsignedSmallInteger('auto_refill_threshold')->default(50)->after('auto_refill_enabled');
            $table->unsignedSmallInteger('auto_refill_amount')->default(100)->after('auto_refill_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['extra_usage_enabled', 'auto_refill_enabled', 'auto_refill_threshold', 'auto_refill_amount']);
        });
    }
};
