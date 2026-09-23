<?php

declare(strict_types=1);

namespace App\Policies;

class ModerationActionPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'moderation.view';

    protected ?string $managePermission = null;
}
