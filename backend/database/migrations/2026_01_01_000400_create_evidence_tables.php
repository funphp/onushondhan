<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('investigation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investigator_id')->constrained()->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_disk', 40);
            $table->string('storage_path', 500);
            $table->string('preview_path', 500)->nullable();
            $table->string('mime_type', 120);
            $table->string('extension', 16);
            $table->string('kind', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 20)->default('pending');
            $table->string('scan_message', 500)->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->string('moderation_status', 20)->default('visible');
            $table->string('caption', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('uploaded_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['investigation_id', 'visibility', 'sort_order']);
            $table->index('sha256');
            $table->index('scan_status');
        });

        Schema::create('evidence_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidence')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('storage_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('mime_type', 120);
            $table->json('metadata_json')->nullable();
            $table->string('scan_status', 20);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['evidence_id', 'version_no']);
        });

        Schema::create('evidence_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidence')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('success');
            $table->string('reason', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['evidence_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_access_logs');
        Schema::dropIfExists('evidence_versions');
        Schema::dropIfExists('evidence');
    }
};
