<?php

namespace App\Notifications;
use Hash;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RegisteredUser extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($data = null)
    {
        $this->data = $data;
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
        ->subject('Confirmation of your inscription ')
        ->line('Your account are created but you need to confirm it. Thanks to click on this link ');
          
        if($this->data) {
                $mailMessage->line('Your password: ' . $this->data);
            }   
        $mailMessage
          ->action('Confirm my Account', url("/confirm/{$notifiable->ref}/".urlencode($notifiable->confirmation_token)))
          ->line('Thank you for using our application!');
        
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
