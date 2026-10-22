<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Interface language chosen by the user (bn|en); drives API messages, notifications and emails.
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('notification_preferences');
        });
        // Categories are platform labels (not user content), so they carry a Bangla name too.
        Schema::table('investigation_categories', function (Blueprint $table) {
            $table->string('name_bn', 120)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('investigation_categories', fn (Blueprint $table) => $table->dropColumn('name_bn'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
