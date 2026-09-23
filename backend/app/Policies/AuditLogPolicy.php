<?php

declare(strict_types=1);

namespace App\Policies;

class AuditLogPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'audit.view';

    protected ?string $managePermission = null;
}
