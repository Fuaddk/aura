<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('usage_label', 100)->nullable()->after('description');
        });

        // Set default labels for existing plans
        DB::table('subscription_plans')->where('slug', 'free')->update(['usage_label' => 'Begrænset forbrug']);
        DB::table('subscription_plans')->where('slug', 'basis')->update(['usage_label' => '10x mere forbrug end gratis']);
        DB::table('subscription_plans')->where('slug', 'pro')->update(['usage_label' => '15x mere forbrug end gratis']);
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('usage_label');
        });
    }
};
