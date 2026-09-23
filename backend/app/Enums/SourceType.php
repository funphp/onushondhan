<?php

declare(strict_types=1);

namespace App\Enums;

enum SourceType: string
{
    use EnumValues;

    case Document = 'document';
    case Website = 'website';
    case NewsArticle = 'news_article';
    case OfficialRecord = 'official_record';
    case Interview = 'interview';
    case Dataset = 'dataset';
    case Academic = 'academic';
    case SocialMedia = 'social_media';
    case Other = 'other';
}
