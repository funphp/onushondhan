<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('investigation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investigator_id')->constrained()->cascadeOnDelete();
            // Null when the tipster is anonymous to the platform (no account).
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('anonymous_to_investigator')->default(true);
            // Hash of the one-time follow-up code given to guest tipsters.
            $table->string('guest_access_hash', 64)->nullable()->unique();
            $table->string('subject', 200);
            $table->text('message');
            $table->string('contact_preference', 30)->default('platform_messaging');
            $table->text('contact_value')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['investigator_id', 'status', 'last_message_at']);
            $table->index('sender_user_id');
        });

        Schema::create('tip_attachments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tip_id')->constrained()->cascadeOnDelete();
            $table->string('storage_disk', 40);
            $table->string('storage_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 20)->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('tip_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 20);
            $table->text('body');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->index(['tip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_messages');
        Schema::dropIfExists('tip_attachments');
        Schema::dropIfExists('tips');
    }
};
