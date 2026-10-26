<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use RuntimeException;

final class PasswordResetTokens
{
    public static function create(User $user): string
    {
        $broker = Password::broker();

        if (! $broker instanceof PasswordBroker) {
            throw new RuntimeException('Password resets require the database token broker.');
        }

        return $broker->createToken($user);
    }
}
