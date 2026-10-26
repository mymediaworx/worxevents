<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkspacePasswordReset extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $hostname,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable instanceof User ? $notifiable->email : '';
        $url = 'https://'.$this->hostname.'/reset-password/'.$this->token.'?email='.urlencode((string) $email);
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your WorxEvents password')
            ->line('Use this link to choose a password for '.$this->hostname.'.')
            ->action('Reset password', $url)
            ->line('This link expires in '.$minutes.' minutes.');
    }
}
