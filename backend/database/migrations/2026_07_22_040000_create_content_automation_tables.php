<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('severity', 20)->default('normal');
            $table->string('fingerprint', 64)->unique();
            $table->string('status', 24)->default('detected')->index();
            $table->json('payload')->nullable();
            $table->string('skip_reason')->nullable();
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('content_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_event_id')->nullable()->constrained('content_events')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('stage', 40)->index();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'stage']);
        });

        Schema::create('content_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('channel', 40);
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'channel']);
        });

        Schema::create('content_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('content_event_id')->nullable()->constrained('content_events')->nullOnDelete();
            $table->string('actor_type', 20)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 60);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('status', 24)->default('published')->after('slug')->index();
            $table->string('event_type', 40)->nullable()->after('category')->index();
            $table->foreignId('content_event_id')->nullable()->after('event_type')->constrained('content_events')->nullOnDelete();
            $table->string('prompt_version', 32)->nullable()->after('author_name');
            $table->string('template_version', 32)->nullable()->after('prompt_version');
            $table->string('ai_provider', 40)->nullable()->after('template_version');
            $table->string('ai_model', 80)->nullable()->after('ai_provider');
            $table->json('seo_payload')->nullable()->after('faq');
            $table->json('link_recommendations')->nullable()->after('seo_payload');
            $table->string('og_image_path')->nullable()->after('meta_description');
            $table->json('social_images')->nullable()->after('og_image_path');
            $table->unsignedSmallInteger('freshness_score')->nullable()->after('view_count');
            $table->timestamp('last_refreshed_at')->nullable()->after('freshness_score');
            $table->timestamp('human_edited_at')->nullable()->after('last_refreshed_at');
            $table->timestamp('approved_at')->nullable()->after('human_edited_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->string('source_fingerprint', 64)->nullable()->unique()->after('approved_by');
        });

        // Existing seeded articles stay publicly visible.
        if (Schema::hasTable('articles')) {
            DB::table('articles')
                ->whereNotNull('published_at')
                ->update(['status' => 'published']);
        }
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_event_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'status',
                'event_type',
                'prompt_version',
                'template_version',
                'ai_provider',
                'ai_model',
                'seo_payload',
                'link_recommendations',
                'og_image_path',
                'social_images',
                'freshness_score',
                'last_refreshed_at',
                'human_edited_at',
                'approved_at',
                'source_fingerprint',
            ]);
        });

        Schema::dropIfExists('content_audits');
        Schema::dropIfExists('content_distributions');
        Schema::dropIfExists('content_jobs');
        Schema::dropIfExists('content_events');
    }
};
