<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ReactionType;
use App\Models\Investigation;
use App\Models\InvestigationCategory;
use App\Models\Investigator;
use App\Models\User;
use App\Services\AgreementService;
use App\Services\CommentService;
use App\Services\EngagementService;
use App\Services\InvestigationService;
use App\Services\InvestigatorService;
use App\Services\TipService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL / DEMO DATA ONLY — never runs in production (see DatabaseSeeder).
 * All accounts share the password below, which is intentionally public.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Onushondhan#2026';

    public function run(): void
    {
        if (User::query()->where('email', 'superadmin@onushondhan.test')->exists()) {
            return; // Already seeded.
        }

        $super = $this->user('Super Admin', 'superadmin', 'superadmin@onushondhan.test', ['super_admin']);
        $this->user('Platform Admin', 'admin', 'admin@onushondhan.test', ['admin']);
        $this->user('Mod Erator', 'moderator', 'moderator@onushondhan.test', ['moderator']);
        $reader = $this->user('Rafiq Reader', 'rafiq_reads', 'reader@onushondhan.test');
        $reader2 = $this->user('Nadia Reader', 'nadia_n', 'reader2@onushondhan.test');

        $investigators = app(InvestigatorService::class);
        $realUser = $this->user('Rahima Akter', 'rahima', 'investigator@onushondhan.test');
        $real = $investigators->onboard($realUser, [
            'public_name' => 'Rahima Akter',
            'bio' => 'Independent journalist covering public procurement and local government in Khulna division.',
            'identity_mode' => 'real_name',
            'website_url' => 'https://example.org/rahima',
            'private_identity' => ['legal_or_private_name' => 'Rahima Akter', 'private_email' => 'rahima.private@example.org'],
        ]);
        $protectedUser = $this->user('Tanvir Hasan', 'tanvir_h', 'investigator2@onushondhan.test');
        $protected = $investigators->onboard($protectedUser, [
            'public_name' => 'River Delta Watch',
            'bio' => 'Documenting pollution and land grabs along the rivers of the delta. Publishing under a protected identity.',
            'identity_mode' => 'protected_public_identity',
            'private_identity' => ['legal_or_private_name' => 'Tanvir Hasan', 'private_phone' => '+8801700000000', 'notes' => 'Prefers contact via platform only.'],
        ]);

        $service = app(InvestigationService::class);
        $statements = array_fill_keys(array_keys(InvestigationService::CONFIRMATION_STATEMENTS), true);
        $cat = fn (string $slug) => InvestigationCategory::query()->where('slug', $slug)->value('id');

        $stories = [
            [$real, $realUser, 'Road repair contracts awarded to a single bidder in Khulna', 'public-procurement', 'governance-public-administration', ['procurement', 'roads', 'khulna'], 'Khulna', 'Khulna'],
            [$real, $realUser, 'Union parishad relief lists and the missing beneficiaries', 'local-government', 'governance-public-administration', ['relief', 'local government'], 'Bagerhat', 'Khulna'],
            [$protected, $protectedUser, 'Tannery effluent in the Dhaleshwari: six months of water samples', 'pollution', 'environment-climate', ['pollution', 'rivers', 'tannery'], 'Savar', 'Dhaka'],
            [$protected, $protectedUser, 'Riverbank sand extraction beyond permitted limits', 'rivers-water', 'environment-climate', ['sand mining', 'rivers'], 'Manikganj', 'Dhaka'],
            [$protected, $protectedUser, 'Expired medicines found on pharmacy shelves in three upazilas', 'medicines', 'health', ['medicines', 'consumer protection'], 'Narayanganj', 'Dhaka'],
            [$real, $realUser, 'School stipend payments delayed for two academic years', 'schools', 'education', ['education', 'stipends'], 'Satkhira', 'Khulna'],
        ];

        $published = [];
        foreach ($stories as $i => [$investigator, $owner, $title, $sub, $parent, $tags, $district, $division]) {
            $inv = $service->createDraft($investigator, [
                'title' => $title,
                'summary' => "This investigation examines {$title}. It sets out the documents obtained, the people interviewed and the open questions for the responsible authorities.",
                'body' => $this->body($title),
                'category_id' => $cat($parent),
                'subcategory_id' => $cat($sub),
                'tags' => $tags,
                'location_name' => "{$district}, Bangladesh",
                'location_country' => 'BD',
                'period_start' => now()->subMonths(8 + $i)->toDateString(),
                'period_end' => now()->subMonths(1)->toDateString(),
                'recommendations' => ['The responsible authority should publish the underlying records.', 'An independent audit should review the process described.'],
                'entities' => [['name' => 'District administration', 'type' => 'government_body', 'role' => 'Responsible authority']],
                'findings' => [
                    ['title' => 'Documents obtained do not match published figures', 'body' => 'Records obtained through a right-to-information request show figures that differ from those published.'],
                    ['title' => 'Stakeholders were not consulted', 'body' => 'Residents interviewed said they were not informed in advance.'],
                ],
                'timeline' => [
                    ['event_date' => now()->subMonths(8)->toDateString(), 'title' => 'Records requested', 'description' => 'Right-to-information request filed.'],
                    ['event_date' => now()->subMonths(4)->toDateString(), 'title' => 'Records received', 'description' => 'Partial records provided.'],
                    ['event_date' => now()->subMonths(1)->toDateString(), 'title' => 'Response sought', 'description' => 'Authorities invited to comment before publication.'],
                ],
                'sources' => [
                    ['title' => 'Right to Information Act, 2009', 'source_type' => 'official_record', 'publisher' => 'Government of Bangladesh', 'url' => 'https://example.org/rti-act', 'is_public' => true],
                    ['title' => 'Interview notes (confidential)', 'source_type' => 'interview', 'is_public' => false],
                ],
                'locations' => [['country' => 'BD', 'region' => $division, 'district' => $district, 'is_primary' => true]],
            ], $owner);
            $published[] = $service->publish($inv->fresh(), $owner, $statements, null);
        }

        // A published update (version 2) and an unpublished draft.
        $first = $published[0];
        $service->saveWorkingCopy($first, ['summary' => $first->summary.' Updated with the authority\'s response received after publication.'], $realUser);
        $service->publish($first->fresh(), $realUser, $statements, 'Added the district office\'s response.');
        $service->createDraft($real, ['title' => 'Draft: hospital procurement of oxygen concentrators', 'summary' => 'Work in progress.'], $realUser);

        // Engagement.
        $engagement = app(EngagementService::class);
        foreach ([$reader, $reader2] as $u) {
            $engagement->follow($u, $protected);
            $engagement->support($u, $protected);
            $engagement->bookmark($u, $published[2]);
            $engagement->react($u, $published[2], ReactionType::Important);
        }
        $engagement->follow($reader, $real);
        $engagement->support($reader, $real);

        $comments = app(CommentService::class);
        $c = $comments->create($published[2], $reader, 'Thank you for publishing the sampling methodology. Were the samples tested by an accredited lab?', null);
        $comments->create($published[2], $protectedUser, 'Yes — the lab accreditation certificate is linked in the sources section.', $c->public_id);
        $comments->create($published[2], $reader2, 'I live near Hemayetpur and the smell has been worse this year.', null);

        // Views/shares for discovery rails.
        foreach ($published as $i => $inv) {
            Investigation::query()->whereKey($inv->id)->update(['views_count' => 40 + $i * 37, 'shares_count' => $i * 3, 'trending_score' => 10 - $i]);
        }
        Investigator::query()->whereKey($protected->id)->update(['total_views' => 300]);
        Investigator::query()->whereKey($real->id)->update(['total_views' => 220]);

        // A guest tip and a member tip (anonymous to the investigator).
        $tips = app(TipService::class);
        $tips->submit(['investigation' => $published[2]->public_id, 'subject' => 'Night-time discharge', 'message' => 'The discharge happens mostly after 11pm from the northern outlet near the bridge.'], null);
        $tips->submit(['investigator' => $real->slug, 'subject' => 'Another contract', 'message' => 'The same bidder won the culvert contract in the neighbouring union as well.', 'anonymous_to_investigator' => true], $reader2);

        DB::table('users')->where('id', $super->id)->update(['last_login_at' => now()]);
        $this->command?->warn('Demo accounts seeded (LOCAL ONLY). Password for all: '.self::PASSWORD);
    }

    /** @param list<string> $roles */
    private function user(string $name, string $username, string $email, array $roles = []): User
    {
        $user = new User(['name' => $name, 'username' => $username, 'email' => $email, 'password' => self::PASSWORD]);
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('user');
        foreach ($roles as $role) {
            $user->assignRole($role);
        }
        app(AgreementService::class)->acceptCurrent($user, config('onushondhan.agreements.user'), 'demo_seed');

        return $user;
    }

    private function body(string $title): string
    {
        return <<<MD
        ## Background

        This report documents findings about **{$title}**. It is based on public records, documents obtained under the Right to Information Act, and interviews with residents and officials.

        ## Method

        1. Records were requested from the responsible offices.
        2. Figures were compared against published notices.
        3. People affected were interviewed; several asked not to be named.

        ## What the records show

        The documents obtained show discrepancies between what was announced and what was recorded. The authorities were invited to respond before publication.

        > Publication on onusondhan.com does not mean the platform has verified these findings. The investigator is responsible for this report.

        ## Open questions

        - Why were the records not published proactively?
        - Who approved the exceptions noted above?
        MD;
    }
}
