<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-publication review: a submitted working copy waits for a moderator before it goes live.
        Schema::table('investigations', function (Blueprint $table) {
            $table->string('review_status', 20)->nullable()->after('status'); // null | pending | rejected
            $table->timestamp('review_requested_at')->nullable()->after('review_status');
            $table->string('review_change_reason', 1000)->nullable()->after('review_requested_at');
            $table->string('review_note', 1000)->nullable()->after('review_change_reason');
            $table->index(['review_status', 'review_requested_at']);
        });
    }

    public function down(): void
    {
        Schema::table('investigations', function (Blueprint $table) {
            $table->dropIndex(['review_status', 'review_requested_at']);
            $table->dropColumn(['review_status', 'review_requested_at', 'review_change_reason', 'review_note']);
        });
    }
};
