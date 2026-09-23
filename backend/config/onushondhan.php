<?php

declare(strict_types=1);

return [
    // Seed local demo accounts/content (never in production regardless of this flag).
    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', true),

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    'vault_disk' => env('VAULT_DISK', 'vault'),

    'evidence' => [
        'max_upload_mb' => (int) env('EVIDENCE_MAX_UPLOAD_MB', 100),
        'require_clean_scan' => (bool) env('EVIDENCE_REQUIRE_CLEAN_SCAN', false),
        'download_link_ttl_minutes' => 10,
        // Detected MIME type => allowed file extensions. Anything else is rejected.
        // SVG/HTML/XML/scripts/executables are intentionally absent (active content).
        'allowed' => [
            'application/pdf' => ['pdf'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'video/mp4' => ['mp4', 'm4v'],
            'video/webm' => ['webm'],
            'video/quicktime' => ['mov'],
            'audio/mpeg' => ['mp3'],
            'audio/mp4' => ['m4a'],
            'audio/x-m4a' => ['m4a'],
            'audio/wav' => ['wav'],
            'audio/x-wav' => ['wav'],
            'audio/ogg' => ['ogg', 'oga'],
            'audio/webm' => ['weba', 'webm'],
            'text/plain' => ['txt', 'csv'],
            'text/csv' => ['csv'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/vnd.oasis.opendocument.text' => ['odt'],
            'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
        ],
    ],

    'tips' => [
        'max_attachment_mb' => (int) env('TIP_ATTACHMENT_MAX_MB', 20),
        'max_attachments' => 5,
    ],

    'scanner' => [
        'driver' => env('MALWARE_SCANNER', 'none'),
        'clamav_socket' => env('CLAMAV_SOCKET', '/var/run/clamav/clamd.ctl'),
        'clamav_binary' => env('CLAMAV_BINARY', '/usr/bin/clamdscan'),
        'timeout_seconds' => 120,
    ],

    'admin' => [
        'mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', true),
    ],

    'analytics' => [
        'fingerprint_key' => env('ANALYTICS_FINGERPRINT_KEY'),
        'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 180),
        'view_dedupe_minutes' => 30,
    ],

    'retention' => [
        'login_logs_days' => (int) env('LOGIN_LOG_RETENTION_DAYS', 365),
        'security_events_days' => 730,
        'closed_tips_days' => 730,
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    // Agreements every registered user must accept, and the extra one investigators need.
    'agreements' => [
        'user' => ['terms_of_use', 'privacy_policy', 'content_policy'],
        'investigator' => ['investigator_responsibility'],
    ],
];
