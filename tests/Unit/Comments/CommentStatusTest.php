<?php

use App\Verso\Comments\Enums\CommentStatus;

it('has labels', function () {
    expect(CommentStatus::Pending->label())->toBe('Pending')
        ->and(CommentStatus::Spam->label())->toBe('Spam');
});

it('knows which statuses are active', function () {
    expect(CommentStatus::Pending->isActive())->toBeTrue()
        ->and(CommentStatus::Approved->isActive())->toBeTrue()
        ->and(CommentStatus::Spam->isActive())->toBeFalse()
        ->and(CommentStatus::Trash->isActive())->toBeFalse();
});
