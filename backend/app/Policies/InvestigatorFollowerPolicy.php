<?php

declare(strict_types=1);

namespace App\Policies;

class InvestigatorFollowerPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'users.view';

    protected ?string $managePermission = null;
}
