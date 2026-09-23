<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('investigation_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('investigations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 24)->unique();
            $table->foreignId('investigator_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('investigation_categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('investigation_categories')->nullOnDelete();

            // Working copy (owner-editable, autosaved).
            $table->string('title', 250);
            $table->string('slug', 270)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('entities')->nullable();
            $table->string('location_name', 200)->nullable();
            $table->string('location_country', 2)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('draft_revision')->default(1);
            $table->boolean('has_unpublished_changes')->default(false);

            // Public projection of the current published version (search + listings).
            $table->string('published_title', 250)->nullable();
            $table->text('published_summary')->nullable();
            $table->longText('published_body')->nullable();

            $table->json('identity_snapshot')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('public');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_published_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->string('withdrawal_reason', 1000)->nullable();
            $table->string('public_status_note', 500)->nullable();
            $table->unsignedInteger('current_version')->default(0);
            $table->boolean('comments_locked')->default(false);

            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('bookmarks_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('tips_count')->default(0);
            $table->double('trending_score')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['category_id', 'published_at']);
            $table->index(['investigator_id', 'status']);
            $table->index('location_country');
            $table->index(['status', 'visibility', 'trending_score']);
            $table->index(['status', 'visibility', 'views_count']);
            $table->index('slug');
        });

        DB::statement('ALTER TABLE investigations ADD FULLTEXT investigations_fulltext (published_title, published_summary, published_body)');

        Schema::create('investigation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title', 250);
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('findings_json')->nullable();
            $table->json('recommendations_json')->nullable();
            $table->json('timeline_json')->nullable();
            $table->json('sources_json')->nullable();
            $table->json('entities_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->json('identity_snapshot')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason', 1000)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['investigation_id', 'version_no']);
        });

        Schema::create('investigation_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('investigation_tag_map', function (Blueprint $table) {
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('investigation_tags')->cascadeOnDelete();
            $table->primary(['investigation_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('investigation_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->string('country', 2)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('area', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['country', 'district']);
            $table->index('investigation_id');
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 250);
            $table->text('body')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['investigation_id', 'sort_order']);
        });

        Schema::create('investigation_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->date('event_date')->nullable();
            $table->string('title', 250);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['investigation_id', 'sort_order']);
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->string('title', 250);
            $table->string('source_type', 40)->default('other');
            $table->string('publisher', 200)->nullable();
            $table->string('url', 2000)->nullable();
            $table->text('citation_text')->nullable();
            $table->date('accessed_at')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['investigation_id', 'sort_order']);
        });

        Schema::create('investigation_publish_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_no');
            $table->foreignId('terms_version_id')->nullable()->constrained('terms_versions')->nullOnDelete();
            $table->json('statements_json');
            $table->timestamp('confirmed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->index(['investigation_id', 'version_no'], 'ipc_investigation_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_publish_confirmations');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('investigation_timelines');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('investigation_locations');
        Schema::dropIfExists('investigation_tag_map');
        Schema::dropIfExists('investigation_tags');
        Schema::dropIfExists('investigation_versions');
        Schema::dropIfExists('investigations');
        Schema::dropIfExists('investigation_categories');
    }
};
