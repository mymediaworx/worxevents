<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Models\PlatformMember;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkspacePasswordReset;
use App\Support\PasswordResetTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreatePlatformAdmin extends Command
{
    protected $signature = 'worxevents:platform-admin {email} {--name=}';

    protected $description = 'Create or reuse a platform super admin. A new user receives a reset link. The password is never printed.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('email=invalid');

            return self::FAILURE;
        }

        $hosts = config('worxevents.platform_domains');
        $host = is_array($hosts) && isset($hosts[0]) && is_string($hosts[0]) ? $hosts[0] : '';

        if ($host === '') {
            $this->error('platform_host=missing');

            return self::FAILURE;
        }

        $created = [];
        $linkSent = false;
        $user = User::query()->where('email', $email)->first();
        $freshUser = false;

        if (! $user instanceof User) {
            $user = User::query()->create([
                'name' => (string) ($this->option('name') ?: 'Platform admin'),
                'email' => $email,
                'password' => Hash::make(Str::random(48)),
            ]);
            $freshUser = true;
            $created[] = 'user';
        }

        $membership = PlatformMember::query()->where('user_id', $user->id)->first();

        if (! $membership instanceof PlatformMember) {
            $role = Role::query()->where('slug', 'platform_super_admin')->firstOrFail();
            PlatformMember::query()->create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => MembershipStatus::Active,
            ]);
            $created[] = 'membership';

            if ($freshUser) {
                $user->notify(new WorkspacePasswordReset(PasswordResetTokens::create($user), $host));
                $linkSent = true;
            }
        }

        $this->line('platform_admin='.$email);
        $this->line('created='.($created === [] ? 'none' : implode(',', $created)));
        $this->line('reset_link_sent='.($linkSent ? 'yes' : 'no'));
        $this->line('platform_host='.$host);

        return self::SUCCESS;
    }
}
