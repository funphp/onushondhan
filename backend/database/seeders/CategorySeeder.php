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

    /** Bangla display names (slugs stay English so URLs are stable). */
    public const BN = [
        'Governance & Public Administration' => 'শাসন ও জনপ্রশাসন',
        'Public Procurement' => 'সরকারি ক্রয়',
        'Public Services' => 'জনসেবা',
        'Local Government' => 'স্থানীয় সরকার',
        'Corruption & Accountability' => 'দুর্নীতি ও জবাবদিহি',
        'Bribery' => 'ঘুষ',
        'Misuse of Public Funds' => 'সরকারি অর্থের অপব্যবহার',
        'Conflicts of Interest' => 'স্বার্থের সংঘাত',
        'Environment & Climate' => 'পরিবেশ ও জলবায়ু',
        'Pollution' => 'দূষণ',
        'Land & Forests' => 'ভূমি ও বন',
        'Rivers & Water' => 'নদী ও পানি',
        'Health' => 'স্বাস্থ্য',
        'Hospitals & Clinics' => 'হাসপাতাল ও ক্লিনিক',
        'Medicines' => 'ওষুধ',
        'Public Health' => 'জনস্বাস্থ্য',
        'Education' => 'শিক্ষা',
        'Schools' => 'বিদ্যালয়',
        'Higher Education' => 'উচ্চশিক্ষা',
        'Examinations' => 'পরীক্ষা',
        'Business & Finance' => 'ব্যবসা ও অর্থনীতি',
        'Banking' => 'ব্যাংকিং',
        'Consumer Protection' => 'ভোক্তা অধিকার',
        'Labour & Workplace' => 'শ্রম ও কর্মক্ষেত্র',
        'Human Rights & Justice' => 'মানবাধিকার ও বিচার',
        'Law Enforcement' => 'আইনশৃঙ্খলা বাহিনী',
        'Courts' => 'আদালত',
        'Minority Rights' => 'সংখ্যালঘু অধিকার',
        'Infrastructure & Development' => 'অবকাঠামো ও উন্নয়ন',
        'Roads & Transport' => 'সড়ক ও পরিবহন',
        'Housing' => 'আবাসন',
        'Energy' => 'জ্বালানি',
        'Technology & Data' => 'প্রযুক্তি ও তথ্য',
        'Privacy & Surveillance' => 'গোপনীয়তা ও নজরদারি',
        'Online Fraud' => 'অনলাইন প্রতারণা',
        'Misinformation' => 'ভুল তথ্য',
        'Other' => 'অন্যান্য',
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::TREE as $parent => $children) {
            $p = InvestigationCategory::query()->updateOrCreate(['slug' => Str::slug($parent)], ['name' => $parent, 'name_bn' => self::BN[$parent], 'sort_order' => $order++, 'status' => 'active']);
            foreach ($children as $i => $child) {
                InvestigationCategory::query()->updateOrCreate(['slug' => Str::slug($child)], ['name' => $child, 'name_bn' => self::BN[$child], 'parent_id' => $p->id, 'sort_order' => $i, 'status' => 'active']);
            }
        }
    }
}
