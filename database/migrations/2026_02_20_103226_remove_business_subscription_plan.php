<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscription_plans')->where('slug', 'business')->delete();
    }

    public function down(): void
    {
        // Intentionally not restored
    }
};
