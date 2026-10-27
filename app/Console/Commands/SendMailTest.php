<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\MailReport;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendMailTest extends Command
{
    protected $signature = 'worxevents:mail-test {email}';

    protected $description = 'Send one plain message through the configured mailer. The SMTP password is never printed.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->line('mail_test=failed');
            $this->line('mail_error=invalid-email');

            return self::FAILURE;
        }

        try {
            $sent = Mail::raw(
                'WorxEvents SMTP is configured. This message confirms the server can deliver mail.',
                function (Message $message) use ($email): void {
                    $message->to($email)->subject('WorxEvents mail test');
                },
            );
        } catch (Throwable $error) {
            $this->line('mail_test=failed');
            $this->line(MailReport::failure($error));
            $reply = MailReport::smtpReply(MailReport::debugFrom($error));

            if ($reply !== '') {
                $this->line('smtp_reply='.$reply);
            }

            return self::FAILURE;
        }

        $this->line('mail_test=sent');
        $this->line('mailer='.(string) config('mail.default'));

        if ($sent instanceof SentMessage) {
            $symfony = $sent->getSymfonySentMessage();
            $id = $symfony->getMessageId();

            if ($id !== '') {
                $this->line('mail_message_id='.$id);
            }

            $reply = MailReport::smtpReply($symfony->getDebug());

            if ($reply !== '') {
                $this->line('smtp_reply='.$reply);
            }
        }

        return self::SUCCESS;
    }
}
