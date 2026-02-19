<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename users columns
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('ai_messages_used',  'ai_tokens_used');
            $table->renameColumn('ai_messages_limit', 'ai_tokens_limit');
        });

        // Rename subscription_plans column
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('messages_limit', 'tokens_limit');
        });

        // Set token limits per plan slug
        DB::table('subscription_plans')->where('slug', 'free')->update(['tokens_limit' => 100000]);
        DB::table('subscription_plans')->where('slug', 'basis')->update(['tokens_limit' => 1000000]);
        DB::table('subscription_plans')->where('slug', 'pro')->update(['tokens_limit' => 2000000]);
        DB::table('subscription_plans')->where('slug', 'business')->update(['tokens_limit' => 0]);

        // Update user token limits based on current subscription_plan
        DB::statement("
            UPDATE users SET ai_tokens_limit = CASE subscription_plan
                WHEN 'free'     THEN 100000
                WHEN 'basis'    THEN 1000000
                WHEN 'pro'      THEN 2000000
                ELSE 9999999
            END
        ");

        // Reset usage counters (old message counts are not comparable to tokens)
        DB::table('users')->update(['ai_tokens_used' => 0]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('ai_tokens_used',  'ai_messages_used');
            $table->renameColumn('ai_tokens_limit', 'ai_messages_limit');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->renameColumn('tokens_limit', 'messages_limit');
        });
    }
};
