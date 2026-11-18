<?php

namespace App\Verso\Comments\Events;

use App\Verso\Comments\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;

// Fired after someone sends a comment, whatever its status.
class CommentSubmitted
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
