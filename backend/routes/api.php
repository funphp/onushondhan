<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminModerationController;
use App\Http\Controllers\Api\V1\AgreementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ComplaintController;
use App\Http\Controllers\Api\V1\EngagementController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InvestigationController;
use App\Http\Controllers\Api\V1\InvestigatorController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\MyInvestigationController;
use App\Http\Controllers\Api\V1\MyInvestigatorController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TaxonomyController;
use App\Http\Controllers\Api\V1\TipController;
use App\Http\Controllers\Api\V1\TipInboxController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Onushondhan REST API — /api/v1
|--------------------------------------------------------------------------
| Public routes expose only published, public-safe data. Everything else is
| behind session (Sanctum SPA) auth + active-account checks, and object-level
| authorisation happens in policies/services, never here alone.
*/

Route::middleware('throttle:api')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // --- Auth (guest) --------------------------------------------------------
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
        Route::post('verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:auth');
    });

    // --- Public content --------------------------------------------------------
    Route::get('agreements', [AgreementController::class, 'current']);
    Route::get('agreements/{type}', [AgreementController::class, 'show'])->where('type', '[a-z_]+');
    Route::get('pages', [PageController::class, 'index']);
    Route::get('pages/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9\-]+');
    Route::get('announcements', [PageController::class, 'announcements']);
    Route::get('sitemap', [PageController::class, 'sitemap']);

    Route::get('categories', [TaxonomyController::class, 'categories']);
    Route::get('categories/{slug}', [TaxonomyController::class, 'category']);
    Route::get('tags', [TaxonomyController::class, 'tags']);
    Route::get('tags/{slug}', [TaxonomyController::class, 'tag']);
    Route::get('locations', [TaxonomyController::class, 'locations']);

    Route::get('investigations', [InvestigationController::class, 'index']);
    Route::get('investigations/{id}', [InvestigationController::class, 'show']);
    Route::get('investigations/{id}/related', [InvestigationController::class, 'related']);
    Route::get('investigations/{id}/versions', [InvestigationController::class, 'versions']);
    Route::get('investigations/{id}/cover', [InvestigationController::class, 'cover']);
    Route::get('investigations/{id}/comments', [CommentController::class, 'index']);
    Route::post('investigations/{id}/view', [InvestigationController::class, 'recordView'])->middleware('throttle:analytics');
    Route::post('investigations/{id}/share', [InvestigationController::class, 'share'])->middleware('throttle:analytics');

    Route::get('investigators', [InvestigatorController::class, 'index']);
    Route::get('investigators/{slug}', [InvestigatorController::class, 'show']);
    Route::get('investigators/{slug}/avatar', [InvestigatorController::class, 'avatar']);
    Route::get('investigators/{slug}/investigations', [InvestigationController::class, 'byInvestigator']);

    Route::get('leaderboards/support', [LeaderboardController::class, 'support']);
    Route::get('leaderboards/{board}', [LeaderboardController::class, 'show']);

    Route::middleware('throttle:downloads')->group(function () {
        Route::get('evidence/{evidence}', [EvidenceController::class, 'show']);
        Route::get('evidence/{evidence}/download', [EvidenceController::class, 'download'])->name('evidence.download');
        Route::get('evidence/{evidence}/preview', [EvidenceController::class, 'preview'])->name('evidence.preview');
    });

    // Tips and complaints accept guests (anonymous to the platform) as well as users.
    Route::post('tips', [TipController::class, 'store'])->middleware('throttle:tips');
    Route::post('tips/access', [TipController::class, 'guestShow'])->middleware('throttle:tip-access');
    Route::post('tips/access/messages', [TipController::class, 'guestReply'])->middleware(['throttle:tip-access', 'throttle:tips']);
    Route::post('complaints', [ComplaintController::class, 'store'])->middleware('throttle:complaints');

    // --- Authenticated ---------------------------------------------------------
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:verification');
        Route::put('auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:auth');

        Route::post('agreements/accept', [AgreementController::class, 'accept']);

        Route::prefix('me')->group(function () {
            Route::patch('profile', [ProfileController::class, 'update']);
            Route::post('phone', [ProfileController::class, 'startPhoneVerification'])->middleware('throttle:verification');
            Route::post('phone/verify', [ProfileController::class, 'confirmPhone'])->middleware('throttle:auth');
            Route::post('deactivate', [ProfileController::class, 'deactivate'])->middleware('throttle:auth');

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id');

            Route::get('bookmarks', [EngagementController::class, 'bookmarks']);
            Route::get('following', [EngagementController::class, 'following']);
            Route::get('supporting', [EngagementController::class, 'supporting']);

            Route::get('tips', [TipController::class, 'mine']);
            Route::get('tips/{tip}', [TipController::class, 'showMine']);
            Route::post('tips/{tip}/messages', [TipController::class, 'replyMine'])->middleware('throttle:tips');
            Route::get('complaints', [ComplaintController::class, 'mine']);
        });

        Route::post('evidence/{evidence}/download-link', [EvidenceController::class, 'downloadLink'])->middleware('throttle:downloads');
        Route::get('tip-attachments/{attachment}/download', [TipInboxController::class, 'downloadAttachment'])->middleware('throttle:downloads');

        // Community actions need a verified email and current agreements.
        Route::middleware(['verified.api', 'agreements'])->group(function () {
            Route::post('investigations/{id}/comments', [CommentController::class, 'store'])->middleware('throttle:comments');
            Route::patch('comments/{comment}', [CommentController::class, 'update'])->middleware('throttle:comments');
            Route::delete('comments/{comment}', [CommentController::class, 'destroy']);
            Route::put('comments/{comment}/vote', [CommentController::class, 'vote'])->middleware('throttle:engagement');
            Route::delete('comments/{comment}/vote', [CommentController::class, 'unvote'])->middleware('throttle:engagement');
            Route::post('comments/{comment}/report', [CommentController::class, 'report'])->middleware('throttle:complaints');

            Route::middleware('throttle:engagement')->group(function () {
                Route::put('investigations/{id}/bookmark', [EngagementController::class, 'bookmark']);
                Route::delete('investigations/{id}/bookmark', [EngagementController::class, 'unbookmark']);
                Route::put('investigations/{id}/reaction', [EngagementController::class, 'react']);
                Route::delete('investigations/{id}/reaction', [EngagementController::class, 'unreact']);
                Route::put('investigators/{slug}/follow', [EngagementController::class, 'follow']);
                Route::delete('investigators/{slug}/follow', [EngagementController::class, 'unfollow']);
            });
            Route::middleware('throttle:support')->group(function () {
                Route::put('investigators/{slug}/support', [EngagementController::class, 'support']);
                Route::delete('investigators/{slug}/support', [EngagementController::class, 'revokeSupport']);
            });

            Route::post('me/investigator', [MyInvestigatorController::class, 'onboard'])->middleware('throttle:auth');
        });

        // --- Investigator workspace -------------------------------------------------
        Route::middleware(['verified.api', 'agreements', 'permission:investigations.manage_own'])->prefix('me')->group(function () {
            Route::get('investigator', [MyInvestigatorController::class, 'show']);
            Route::patch('investigator', [MyInvestigatorController::class, 'update']);
            Route::post('investigator/avatar', [MyInvestigatorController::class, 'avatar'])->middleware('throttle:uploads');
            Route::get('investigator/private-identity', [MyInvestigatorController::class, 'privateIdentity']);
            Route::put('investigator/private-identity', [MyInvestigatorController::class, 'updatePrivateIdentity']);
            Route::get('investigator/analytics', [MyInvestigatorController::class, 'analytics']);
            Route::get('investigator/followers', [MyInvestigatorController::class, 'followers']);
            Route::get('investigator/supporters', [MyInvestigatorController::class, 'supporters']);

            Route::get('investigations/confirmation-statements', [MyInvestigationController::class, 'confirmationStatements']);
            Route::get('investigations', [MyInvestigationController::class, 'index']);
            Route::post('investigations', [MyInvestigationController::class, 'store']);
            Route::get('investigations/{investigation}', [MyInvestigationController::class, 'show']);
            Route::patch('investigations/{investigation}', [MyInvestigationController::class, 'update'])->middleware('throttle:autosave');
            Route::delete('investigations/{investigation}', [MyInvestigationController::class, 'destroy']);
            Route::get('investigations/{investigation}/preview', [MyInvestigationController::class, 'preview']);
            Route::post('investigations/{investigation}/publish', [MyInvestigationController::class, 'publish']);
            Route::post('investigations/{investigation}/withdraw', [MyInvestigationController::class, 'withdraw']);
            Route::post('investigations/{investigation}/archive', [MyInvestigationController::class, 'archive']);
            Route::get('investigations/{investigation}/versions', [MyInvestigationController::class, 'versions']);
            Route::get('investigations/{investigation}/versions/{versionNo}', [MyInvestigationController::class, 'version'])->whereNumber('versionNo');
            Route::get('investigations/{investigation}/diff', [MyInvestigationController::class, 'diff']);
            Route::get('investigations/{investigation}/cover', [MyInvestigationController::class, 'cover']);
            Route::post('investigations/{investigation}/cover', [MyInvestigationController::class, 'uploadCover'])->middleware('throttle:uploads');
            Route::delete('investigations/{investigation}/cover', [MyInvestigationController::class, 'removeCover']);

            Route::get('investigations/{investigation}/evidence', [EvidenceController::class, 'index']);
            Route::post('investigations/{investigation}/evidence', [EvidenceController::class, 'store'])->middleware('throttle:uploads');
            Route::patch('evidence/{evidence}', [EvidenceController::class, 'update']);
            Route::delete('evidence/{evidence}', [EvidenceController::class, 'destroy']);
            Route::post('evidence/{evidence}/versions', [EvidenceController::class, 'replace'])->middleware('throttle:uploads');
            Route::get('evidence/{evidence}/versions', [EvidenceController::class, 'versions']);
            Route::get('evidence/{evidence}/access-log', [EvidenceController::class, 'accessLog']);

            Route::get('inbox/tips', [TipInboxController::class, 'index']);
            Route::get('inbox/tips/{tip}', [TipInboxController::class, 'show']);
            Route::patch('inbox/tips/{tip}', [TipInboxController::class, 'update']);
            Route::post('inbox/tips/{tip}/messages', [TipInboxController::class, 'reply'])->middleware('throttle:tips');
        });

        // --- Staff moderation API (the Filament panel is the primary admin UI) ---------
        Route::prefix('admin')->middleware('verified.api')->group(function () {
            Route::get('moderation/queue', [AdminModerationController::class, 'queue'])->middleware('permission:moderation.view');
            Route::get('complaints', [AdminModerationController::class, 'complaints'])->middleware('permission:complaints.manage');
            Route::get('complaints/{complaint}', [AdminModerationController::class, 'complaint'])->middleware('permission:complaints.manage');
            Route::patch('complaints/{complaint}', [AdminModerationController::class, 'updateComplaint'])->middleware('permission:complaints.manage');
            Route::post('moderation/actions', [AdminModerationController::class, 'act'])->middleware('permission:moderation.view');
            Route::get('audit-logs', [AdminModerationController::class, 'auditLogs'])->middleware('permission:audit.view');
            Route::post('investigators/{investigatorPublicId}/private-identity', [AdminModerationController::class, 'revealIdentity'])->middleware('permission:identity.view_private');
        });
    });
});
