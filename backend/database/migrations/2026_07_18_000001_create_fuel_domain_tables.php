<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 32);
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->string('customer_service')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category', 32); // petrol|diesel|gas|industrial|other
            $table->string('unit', 16)->default('Rs/L');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();
        });

        Schema::create('fuel_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('fuel_companies')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('fuel_type_id')->constrained('fuel_types')->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->string('currency', 8)->default('PKR');
            $table->string('unit', 16)->default('Rs/L');
            $table->date('effective_date')->nullable();
            $table->string('source', 64)->default('trackmate');
            $table->string('source_url')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'city_id', 'fuel_type_id', 'source'], 'fuel_prices_unique_lookup');
            $table->index(['fuel_type_id', 'effective_date']);
        });

        Schema::create('fuel_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fuel_price_id')->constrained('fuel_prices')->cascadeOnDelete();
            $table->decimal('previous_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2);
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->decimal('change_percentage', 8, 4)->default(0);
            $table->timestamp('effective_at');
            $table->timestamps();

            $table->index(['fuel_price_id', 'effective_at']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('status', 32); // ok|failed|partial
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('fuel_price_histories');
        Schema::dropIfExists('fuel_prices');
        Schema::dropIfExists('fuel_types');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('fuel_companies');
    }
};
