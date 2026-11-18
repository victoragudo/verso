<?php

namespace App\Verso\Users\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

// Thrown when a change would leave the site without any admin.
class LastAdminException extends RuntimeException
{
    public static function forRoleChange(): self
    {
        return new self(__('The site needs at least one admin. Make another user an admin first.'));
    }

    public static function forDelete(): self
    {
        return new self(__('You cannot delete the last admin of the site.'));
    }

    // Show the problem as a normal form error instead of a server error.
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $this->getMessage(), 'errors' => ['role' => [$this->getMessage()]]], 422);
        }

        return back()->withErrors(['role' => $this->getMessage()]);
    }
}
