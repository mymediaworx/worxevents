<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WorkspacePasswordReset;
use App\Support\MailReport;
use App\Support\PasswordResetTokens;
use Illuminate\Console\Command;
use Throwable;

final class SendPasswordReset extends Command
{
    protected $signature = 'worxevents:password-reset {email}';

    protected $description = 'Email a fresh password reset link. The link is never printed.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->line('reset_link_sent=no');
            $this->line('mail_error=invalid-email');

            return self::FAILURE;
        }

        $hosts = config('worxevents.platform_domains');
        $host = is_array($hosts) && isset($hosts[0]) && is_string($hosts[0]) ? $hosts[0] : '';

        if ($host === '') {
            $this->line('reset_link_sent=no');
            $this->line('platform_host=missing');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->line('reset_link_sent=no');
            $this->line('reset_user=missing');

            return self::FAILURE;
        }

        try {
            $user->notify(new WorkspacePasswordReset(PasswordResetTokens::create($user), $host));
        } catch (Throwable $error) {
            $this->line('reset_link_sent=no');
            $this->line(MailReport::failure($error));
            $reply = MailReport::smtpReply(MailReport::debugFrom($error));

            if ($reply !== '') {
                $this->line('smtp_reply='.$reply);
            }

            return self::FAILURE;
        }

        $this->line('reset_link_sent=yes');
        $this->line('platform_host='.$host);

        return self::SUCCESS;
    }
}
