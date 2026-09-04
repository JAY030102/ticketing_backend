<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoogleAccountVerifiedNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Email Address Is Verified')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your account was created using Google Sign-In, and your email address '.$notifiable->email.' has already been verified through your Google account.')
            ->line('No further action is needed — you\'re all set on the email verification step.')
            ->line('If you did not create this account, please contact support immediately.');
    }
}
