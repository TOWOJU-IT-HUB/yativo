<?php

namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomNotification extends Notification
{
    public function __construct(protected array $message) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject($this->message['subject'])
            ->greeting($this->message['greeting'])
            ->line($this->message['line1'])
            ->line($this->message['line2'])
            ->line($this->message['line3'])
            ->line($this->message['line4'])
            ->salutation($this->message['salutation']);
    }
}
