<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->jsonb('feature_flags')->nullable()->after('tokens_limit');
        });

        $defaults = [
            'free'  => ['calendar' => false, 'inbox' => false],
            'basis' => ['calendar' => true,  'inbox' => false],
            'pro'   => ['calendar' => true,  'inbox' => true],
        ];

        foreach ($defaults as $slug => $flags) {
            DB::table('subscription_plans')
                ->where('slug', $slug)
                ->update(['feature_flags' => json_encode($flags)]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('feature_flags');
        });
    }
};
