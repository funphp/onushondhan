<?php

declare(strict_types=1);

namespace App\Policies;

class ComplaintPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'complaints.manage';

    protected ?string $managePermission = null;
}
