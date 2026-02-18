<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        $keys = [
            ['key' => 'stripe_key',            'is_secret' => false],
            ['key' => 'stripe_secret',         'is_secret' => true],
            ['key' => 'stripe_webhook_secret', 'is_secret' => true],
            ['key' => 'google_client_id',      'is_secret' => false],
            ['key' => 'google_client_secret',  'is_secret' => true],
            ['key' => 'openai_api_key',        'is_secret' => true],
            ['key' => 'anthropic_api_key',     'is_secret' => true],
            ['key' => 'mistral_api_key',       'is_secret' => true],
        ];

        foreach ($keys as $row) {
            DB::table('app_settings')->insert([
                'key'        => $row['key'],
                'value'      => null,
                'is_secret'  => $row['is_secret'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
