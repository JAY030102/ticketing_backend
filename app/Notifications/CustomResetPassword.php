<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends BaseResetPassword
{
    public function toMail($notifiable)
    {
        $url = config('app.frontend_url')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in 60 minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
