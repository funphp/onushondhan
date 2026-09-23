<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('visible');
            $table->boolean('is_pinned')->default(false);
            $table->integer('score')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['investigation_id', 'parent_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('comment_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('value');
            $table->timestamps();
            $table->unique(['comment_id', 'user_id']);
        });

        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['comment_id', 'reporter_user_id']);
            $table->index(['status', 'created_at']);
        });

        // One row per (user, investigator) — re-supporting reactivates the same row,
        // so toggling can never inflate the count beyond one per user.
        Schema::create('investigator_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigator_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('toggle_count')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unique(['user_id', 'investigator_id']);
            $table->index(['investigator_id', 'status']);
        });

        Schema::create('investigator_followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigator_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'investigator_id']);
            $table->index('investigator_id');
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'investigation_id']);
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->string('reaction_type', 20);
            $table->timestamps();
            $table->unique(['user_id', 'investigation_id']);
            $table->index(['investigation_id', 'reaction_type']);
        });

        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('referrer', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['investigation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shares');
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('bookmarks');
        Schema::dropIfExists('investigator_followers');
        Schema::dropIfExists('investigator_supports');
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_votes');
        Schema::dropIfExists('comments');
    }
};
