<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('public_name', 120);
            $table->string('slug', 140)->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('identity_mode', 40)->default('protected_public_identity');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('supporters_count')->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('published_investigations_count')->default(0);
            $table->unsignedBigInteger('total_views')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'supporters_count']);
        });

        // Spec table name kept verbatim. Columns are application-encrypted.
        Schema::create('investigator_private_identity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investigator_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('legal_or_private_name')->nullable();
            $table->text('private_email')->nullable();
            $table->text('private_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('terms_versions', function (Blueprint $table) {
            $table->id();
            $table->string('agreement_type', 64);
            $table->string('version', 32);
            $table->string('title');
            $table->longText('content');
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['agreement_type', 'version']);
            $table->index(['agreement_type', 'status', 'published_at']);
        });

        Schema::create('terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('agreement_type', 64);
            $table->foreignId('terms_version_id')->constrained('terms_versions')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'agreement_type', 'accepted_at']);
            $table->unique(['user_id', 'terms_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptances');
        Schema::dropIfExists('terms_versions');
        Schema::dropIfExists('investigator_private_identity');
        Schema::dropIfExists('investigators');
    }
};
