<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InviteUserNotification extends Notification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */

    public $user, $password;
    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
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
        // This will be sent in mail notification
        return (new MailMessage)
            ->greeting('Hi ' . $this->user->name)
            ->subject('Welcome To ShipCash')
            ->line("Congrats, You have successfully registered on website. To Change the password please click here !")
            ->line('Mail: ' . $this->user->email)
            ->line('Password: ' . $this->password)
            ->line('If you did not request a password reset, no further action is required.');
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
