<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(protected string $status) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url').'/login';

        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject('Your Account Has Been Approved')
                ->greeting('Hello '.$notifiable->name.'!')
                ->line('Good news — your account has been approved by an administrator.')
                ->action('Log In', $loginUrl)
                ->line('You can now log in and start using the system.');
        }

        return (new MailMessage)
            ->subject('Update on Your Account Registration')
            ->greeting('Hello '.$notifiable->name.'.')
            ->line('Unfortunately, your account registration was not approved at this time.')
            ->line('If you believe this is a mistake, please contact support.');
    }
}
