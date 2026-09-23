<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\CommentStatus;
use App\Enums\ComplaintStatus;
use App\Enums\ScanStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Complaint;
use App\Models\Evidence;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ModerationQueue extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Moderation queue';

    public static function canView(): bool
    {
        return Auth::user()?->hasPermission('moderation.view') ?? false;
    }

    protected function getStats(): array
    {
        $open = Complaint::query()->whereIn('status', [ComplaintStatus::Submitted, ComplaintStatus::UnderReview, ComplaintStatus::Escalated])->count();

        return [
            Stat::make('Open complaints', $open)->color($open > 0 ? 'warning' : 'success'),
            Stat::make('Open comment reports', CommentReport::query()->where('status', 'open')->count()),
            Stat::make('Comments pending review', Comment::query()->where('status', CommentStatus::Pending)->count()),
            Stat::make('Quarantined evidence', Evidence::query()->where('scan_status', ScanStatus::Infected)->count())->color('danger'),
            Stat::make('Failed scans', Evidence::query()->where('scan_status', ScanStatus::Failed)->count()),
        ];
    }
}
