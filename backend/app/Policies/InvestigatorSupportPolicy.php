<?php

declare(strict_types=1);

namespace App\Policies;

class InvestigatorSupportPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'users.view';

    protected ?string $managePermission = null;
}
