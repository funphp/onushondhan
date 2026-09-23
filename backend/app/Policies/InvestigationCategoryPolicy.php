<?php

declare(strict_types=1);

namespace App\Policies;

class InvestigationCategoryPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'cms.manage';

    protected ?string $managePermission = 'cms.manage';
}
