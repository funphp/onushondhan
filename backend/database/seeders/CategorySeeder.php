<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InvestigationCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public const TREE = [
        'Governance & Public Administration' => ['Public Procurement', 'Public Services', 'Local Government'],
        'Corruption & Accountability' => ['Bribery', 'Misuse of Public Funds', 'Conflicts of Interest'],
        'Environment & Climate' => ['Pollution', 'Land & Forests', 'Rivers & Water'],
        'Health' => ['Hospitals & Clinics', 'Medicines', 'Public Health'],
        'Education' => ['Schools', 'Higher Education', 'Examinations'],
        'Business & Finance' => ['Banking', 'Consumer Protection', 'Labour & Workplace'],
        'Human Rights & Justice' => ['Law Enforcement', 'Courts', 'Minority Rights'],
        'Infrastructure & Development' => ['Roads & Transport', 'Housing', 'Energy'],
        'Technology & Data' => ['Privacy & Surveillance', 'Online Fraud', 'Misinformation'],
        'Other' => [],
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::TREE as $parent => $children) {
            $p = InvestigationCategory::query()->updateOrCreate(['slug' => Str::slug($parent)], ['name' => $parent, 'sort_order' => $order++, 'status' => 'active']);
            foreach ($children as $i => $child) {
                InvestigationCategory::query()->updateOrCreate(['slug' => Str::slug($child)], ['name' => $child, 'parent_id' => $p->id, 'sort_order' => $i, 'status' => 'active']);
            }
        }
    }
}
