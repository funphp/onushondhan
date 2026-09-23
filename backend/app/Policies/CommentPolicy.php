<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('comments.moderate');
    }

    public function view(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id || $user->hasPermission('comments.moderate');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('comments.create');
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id && $user->hasPermission('comments.manage_own');
    }

    public function delete(User $user, Comment $comment): bool
    {
        return ($comment->user_id === $user->id && $user->hasPermission('comments.manage_own'))
            || $user->hasPermission('comments.moderate');
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->hasPermission('comments.moderate');
    }
}
