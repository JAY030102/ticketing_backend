<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AccountStatusUpdated extends Notification
{
    use Queueable;

    public function __construct(protected string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url') . '/login';

        return match ($this->status) {
            'approved' => (new MailMessage)
                ->subject('Your Account Has Been Approved')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Good news — your account has been approved by an administrator.')
                ->action('Log In', $loginUrl)
                ->line('You can now log in and start using the system.'),

            'rejected' => (new MailMessage)
                ->subject('Update on Your Account Registration')
                ->greeting('Hello ' . $notifiable->name . '.')
                ->line('Unfortunately, your account registration was not approved at this time.')
                ->line('If you believe this is a mistake, please contact support.'),

            'active' => (new MailMessage)
                ->subject('Your Account Has Been Reactivated')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Your account has been reactivated by an administrator.')
                ->action('Log In', $loginUrl)
                ->line('You can now log in as usual.'),

            'suspended' => (new MailMessage)
                ->subject('Your Account Has Been Suspended')
                ->greeting('Hello ' . $notifiable->name . '.')
                ->line('Your account has been temporarily suspended by an administrator.')
                ->line('Please contact support if you have questions about this action.'),

            'terminated' => (new MailMessage)
                ->subject('Your Account Has Been Terminated')
                ->greeting('Hello ' . $notifiable->name . '.')
                ->line('Your account has been terminated and is no longer active.')
                ->line('If you believe this is a mistake, please contact support.'),

            default => (new MailMessage)
                ->subject('Account Status Update')
                ->greeting('Hello ' . $notifiable->name . '.')
                ->line('Your account status has changed.'),
        };
    }
}
