<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The site is always named "onusondhan.com" (Latin script, in every interface language).
     * Rewrites the old name in the seeded informational pages. Legal pages are left alone: they
     * mirror agreement versions users accepted and change only through a new reviewed version.
     */
    public function up(): void
    {
        foreach (['title', 'excerpt', 'body', 'seo_title', 'seo_description'] as $column) {
            DB::table('cms_pages')
                ->where('page_type', '!=', 'legal')
                ->where($column, 'like', '%Onushondhan%')
                ->update([$column => DB::raw("REPLACE(`{$column}`, 'Onushondhan', 'onusondhan.com')")]);
        }
    }

    public function down(): void
    {
        // Content change only; not reversed automatically.
    }
};
