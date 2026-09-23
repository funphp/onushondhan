<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AgreementType;
use App\Models\CmsPage;
use App\Models\TermsVersion;
use Illuminate\Database\Seeder;

/**
 * Seeds v1.0 agreement templates and CMS pages from resources/content. Every legal
 * text is a template flagged for legal review. Idempotent: never overwrites edits
 * made through the admin panel.
 */
class ContentSeeder extends Seeder
{
    private const AGREEMENTS = [
        'terms_of_use' => 'Terms of Use',
        'privacy_policy' => 'Privacy Policy',
        'content_policy' => 'Content Policy',
        'investigator_responsibility' => 'Investigator Responsibility Agreement',
    ];

    private const PAGES = [
        'about' => ['About Onushondhan', 'What Onushondhan is — and what it is not.'],
        'how-it-works' => ['How It Works', 'Publishing, reading, tips and support on Onushondhan.'],
        'faq' => ['Frequently Asked Questions', 'Answers to common questions about Onushondhan.'],
        'contact' => ['Contact', 'How to reach the Onushondhan team.'],
    ];

    public function run(): void
    {
        foreach (self::AGREEMENTS as $type => $title) {
            $content = (string) file_get_contents(resource_path("content/legal/{$type}.md"));
            $version = TermsVersion::query()->firstOrNew(['agreement_type' => AgreementType::from($type), 'version' => '1.0']);
            if (! $version->exists) {
                $version->fill(['title' => $title, 'content' => $content, 'effective_at' => now()->subDay()]);
                $version->status = 'published';
                $version->published_at = now()->subDay();
                $version->save();
            }

            // Mirror each agreement as a public CMS page for reading/linking.
            $slug = str_replace('_', '-', $type);
            CmsPage::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'excerpt' => $title.' (template — requires legal review).',
                'body' => $content,
                'page_type' => 'legal',
                'requires_legal_review' => true,
                'status' => 'published',
                'published_at' => now()->subDay(),
            ]);
        }

        foreach (self::PAGES as $slug => [$title, $excerpt]) {
            CmsPage::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => (string) file_get_contents(resource_path("content/pages/{$slug}.md")),
                'page_type' => 'page',
                'requires_legal_review' => $slug === 'contact',
                'status' => 'published',
                'published_at' => now()->subDay(),
            ]);
        }

        CmsPage::query()->firstOrCreate(['slug' => 'welcome-to-onushondhan'], [
            'title' => 'Welcome to Onushondhan',
            'excerpt' => 'A home for independent investigations.',
            'body' => "Onushondhan is now open for independent investigators to publish their work.\n\nRemember: publication on Onushondhan is not verification or endorsement — investigators are responsible for their reports.",
            'page_type' => 'announcement',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }
}
