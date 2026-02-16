<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_plan')->default('free')->after('email');
            $table->decimal('wallet_balance', 10, 2)->default(0.00)->after('subscription_plan');
            $table->integer('ai_messages_used')->default(0)->after('wallet_balance');
            $table->integer('ai_messages_limit')->default(50)->after('ai_messages_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan', 'wallet_balance', 'ai_messages_used', 'ai_messages_limit']);
        });
    }
};
