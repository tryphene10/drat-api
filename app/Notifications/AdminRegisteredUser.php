<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminRegisteredUser extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($password= null)
    {
        $this->_password = $password;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {

        $mailMessage = new MailMessage();

        $mailMessage
            ->subject('Email Verification')
            ->line('Votre compte a été créé ave le mot de passe: '.$this->_password.'. S\'il vous plait veillez cliquer sur ce lien pour valider votre inscription:  ')
            ->action('Confirmation de compte', url("/confirm/{$notifiable->ref}/".urlencode($notifiable->confirmation_token)))
            ->line('NSUL LAAM');

        return $mailMessage;
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
