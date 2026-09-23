<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * InnoDB only indexes FULLTEXT rows on commit, so search tests run outside the
 * per-test transaction and clean up after themselves.
 */
abstract class SearchTestCase extends TestCase
{
    protected array $connectionsToTransact = [];

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['analytics_events', 'audit_logs', 'investigation_publish_confirmations', 'investigation_versions', 'sources', 'findings', 'investigation_timelines', 'investigation_tag_map', 'investigation_locations', 'investigations', 'investigation_tags', 'investigator_private_identity', 'investigators', 'terms_acceptances', 'user_roles', 'notifications', 'jobs'] as $table) {
            DB::table($table)->delete();
        }
        DB::table('audit_chain')->update(['last_hash' => null, 'last_id' => null]);
        User::query()->withTrashed()->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }
}
