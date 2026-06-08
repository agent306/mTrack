<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLink extends Notification
{
    use Queueable;

    public function __construct(private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your mTrack sign-in link')
            ->line('Use this secure link to sign in to mTrack.')
            ->action('Sign in to mTrack', $this->url)
            ->line('The link expires shortly. You can request a new one from the sign-in page.');
    }
}
