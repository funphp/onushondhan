<?php

declare(strict_types=1);

namespace App\Enums;

enum CommentReportReason: string
{
    use EnumValues;

    case Spam = 'spam';
    case Harassment = 'harassment';
    case HateSpeech = 'hate_speech';
    case PersonalInformation = 'personal_information';
    case Misinformation = 'misinformation';
    case OffTopic = 'off_topic';
    case Other = 'other';
}
