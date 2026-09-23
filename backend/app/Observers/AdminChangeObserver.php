<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits every change to administrator-managed configuration (CMS, agreements,
 * categories, settings, roles) regardless of which code path made it.
 */
class AdminChangeObserver
{
    private const IGNORE = ['updated_at', 'created_at', 'body', 'content'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->log($this->prefix($model).'.created', $model, null, $this->summarise($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $before = array_intersect_key($model->getOriginal(), $changes);
        foreach (['body', 'content'] as $large) {
            if (array_key_exists($large, $changes)) {
                $before[$large] = hash('sha256', (string) ($model->getOriginal($large) ?? ''));
                $changes[$large] = hash('sha256', (string) $changes[$large]);
            }
        }
        unset($changes['updated_at'], $before['updated_at']);
        if ($changes !== []) {
            $this->audit->log($this->prefix($model).'.updated', $model, $before, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->audit->log($this->prefix($model).'.deleted', $model, $this->summarise($model->getAttributes()), null);
    }

    private function prefix(Model $model): string
    {
        return 'admin.'.strtolower(class_basename($model));
    }

    /** @param array<string, mixed> $attributes */
    private function summarise(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORE));
    }
}
