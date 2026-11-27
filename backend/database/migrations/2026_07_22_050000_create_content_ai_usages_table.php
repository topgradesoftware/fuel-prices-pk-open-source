<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('content_event_id')->nullable()->constrained('content_events')->nullOnDelete();
            $table->string('provider', 40);
            $table->string('model', 80)->nullable();
            $table->string('event_type', 40)->nullable()->index();
            $table->string('status', 24)->default('success'); // success|fallback|blocked_budget|failed
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 10, 6)->default(0);
            $table->string('period_month', 7)->index(); // Y-m
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['period_month', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ai_usages');
    }
};
