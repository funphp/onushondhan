<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TermsStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TermsVersionPolicy extends AdminResourcePolicy
{
    protected string $viewPermission = 'terms.manage';

    protected ?string $managePermission = 'terms.manage';

    /** Published agreement text is immutable — publish a new version instead. */
    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $model->getAttribute('status') === TermsStatus::Draft;
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
