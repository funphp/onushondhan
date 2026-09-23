<?php

declare(strict_types=1);

namespace App\Policies;

class SystemSettingPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'settings.manage';

    protected ?string $managePermission = 'settings.manage';
}
