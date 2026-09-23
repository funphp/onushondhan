<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('comment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidence')->nullOnDelete();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_email')->nullable();
            $table->string('complaint_type', 40);
            $table->text('description');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('reporter_user_id');
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->string('action', 40);
            $table->string('reason', 1000);
            $table->json('details')->nullable();
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['target_type', 'target_id']);
            $table->index('created_at');
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 64)->nullable();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investigator_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 40);
            $table->string('page_url', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->char('anonymous_fingerprint', 64)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['event_name', 'created_at']);
            $table->index(['investigation_id', 'event_name', 'created_at']);
            $table->index(['investigator_id', 'event_name', 'created_at']);
        });

        // Append-only. Each row carries a hash chained to the previous row so
        // tampering is detectable with `php artisan audit:verify`.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);
            $table->timestamp('created_at', 6)->nullable();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        // Single-row chain head, locked FOR UPDATE while appending so concurrent
        // transactions can never fork the chain.
        Schema::create('audit_chain', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->char('last_hash', 64)->nullable();
            $table->unsignedBigInteger('last_id')->nullable();
        });
        DB::table('audit_chain')->insert(['id' => 1, 'last_hash' => null, 'last_id' => null]);

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_attempted')->nullable();
            $table->boolean('success');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('failure_reason', 60)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 60);
            $table->string('severity', 10)->default('info');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 250);
            $table->string('excerpt', 1000)->nullable();
            $table->longText('body');
            $table->string('page_type', 20)->default('page');
            $table->boolean('requires_legal_review')->default(false);
            $table->string('status', 20)->default('draft');
            $table->string('seo_title', 250)->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['page_type', 'status', 'published_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('value_json')->nullable();
            $table->string('value_type', 20)->default('string');
            $table->string('description', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('cms_pages');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('login_logs');
        Schema::dropIfExists('audit_chain');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('notifications');
    }
};
