<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header image. Like title/body, the working copy and the published copy are kept apart so
        // a new cover only goes live when the investigator publishes. Files live in the private vault.
        Schema::table('investigations', function (Blueprint $table) {
            $table->string('cover_path', 255)->nullable()->after('body');
            $table->string('published_cover_path', 255)->nullable()->after('published_body');
        });
    }

    public function down(): void
    {
        Schema::table('investigations', fn (Blueprint $table) => $table->dropColumn(['cover_path', 'published_cover_path']));
    }
};
