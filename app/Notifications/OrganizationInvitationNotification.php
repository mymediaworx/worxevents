<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganizationInvitationNotification extends Notification
{
    public function __construct(
        public readonly string $token,
        public readonly string $hostname,
        public readonly string $organizationName,
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
        $url = 'https://'.$this->hostname.'/invitations/'.$this->token;

        return (new MailMessage)
            ->subject('You are invited to '.$this->organizationName)
            ->line($this->organizationName.' invited you to WorxEvents.')
            ->action('Review invitation', $url)
            ->line('The invitation expires in 7 days.');
    }
}
