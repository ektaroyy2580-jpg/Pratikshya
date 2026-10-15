<?php

namespace App\Policies;

use App\Models\BookBookmark;
use App\Models\User;

/** Bookmarks are private to their owner — admins included. */
class BookBookmarkPolicy
{
    public function view(User $user, BookBookmark $bookmark): bool
    {
        return (int) $bookmark->user_id === (int) $user->id;
    }

    public function delete(User $user, BookBookmark $bookmark): bool
    {
        return (int) $bookmark->user_id === (int) $user->id;
    }
}
