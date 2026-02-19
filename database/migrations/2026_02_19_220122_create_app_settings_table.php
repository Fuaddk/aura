<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Insert extra_usage_rate_per_token if not already present
        if (!DB::table('app_settings')->where('key', 'extra_usage_rate_per_token')->exists()) {
            DB::table('app_settings')->insert([
                'key'        => 'extra_usage_rate_per_token',
                'value'      => '0.0004',
                'is_secret'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'extra_usage_rate_per_token')->delete();
    }
};
