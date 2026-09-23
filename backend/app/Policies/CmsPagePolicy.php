<?php

declare(strict_types=1);

namespace App\Policies;

class CmsPagePolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'cms.manage';

    protected ?string $managePermission = 'cms.manage';
}
