<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->integer('messages_limit')->default(50); // 0 = unlimited
            $table->json('features')->nullable();
            $table->string('stripe_price_id', 200)->nullable();
            $table->string('color', 20)->default('#9ca3af');
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('subscription_plans')->insert([
            [
                'slug'            => 'free',
                'name'            => 'Gratis',
                'description'     => 'Kom i gang med grundlæggende rådgivning',
                'price'           => 0,
                'messages_limit'  => 50,
                'features'        => json_encode(['50 AI-beskeder om måneden', '1 aktiv sag', 'Grundlæggende opgavestyring', 'Dokumentopbevaring']),
                'stripe_price_id' => null,
                'color'           => '#9ca3af',
                'is_popular'      => false,
                'is_active'       => true,
                'sort_order'      => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'slug'            => 'pro',
                'name'            => 'Pro',
                'description'     => 'Alt hvad du behøver gennem skilsmissen',
                'price'           => 99,
                'messages_limit'  => 500,
                'features'        => json_encode(['500 AI-beskeder om måneden', 'Ubegrænset sager', 'Avancerede opgaver', 'Dokumentupload', 'Kalenderintegration', 'E-mail indbakke']),
                'stripe_price_id' => null,
                'color'           => '#7E75CE',
                'is_popular'      => true,
                'is_active'       => true,
                'sort_order'      => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'slug'            => 'business',
                'name'            => 'Business',
                'description'     => 'Ubegrænset adgang til alle funktioner',
                'price'           => 299,
                'messages_limit'  => 0,
                'features'        => json_encode(['Ubegrænset AI-beskeder', 'Ubegrænset sager', 'Prioritetssupport', 'API-adgang', 'Alle Pro-funktioner', 'Tidlig adgang til nye funktioner']),
                'stripe_price_id' => null,
                'color'           => '#5BC4E8',
                'is_popular'      => false,
                'is_active'       => true,
                'sort_order'      => 2,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
