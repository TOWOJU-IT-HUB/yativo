<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $amount, $type, $wallet;
    /**
     * Create a new notification instance.
     */
    public function __construct($amount, $type, $wallet)
    {
        $this->amount = $amount;
        $this->type = $type;
        $this->wallet = strtoupper($wallet);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Transaction Notification - $this->type of $this->wallet $this->amount in Your Account")
            ->greeting("Dear $notifiable->name,")
            ->line("We hope this email finds you well. We want to inform you about a recent transaction in your account with ".getenv('APP_NAME').". Here are the details:\n\n")
            ->line("Transaction Type: ".ucwords($this->type))
            ->line("Amount: ".$this->wallet.$this->amount)
            ->line("Transaction Date and Time: ". now())
            ->line("\n\nIf you have any questions or concerns regarding this transaction, please feel free to contact our customer support at support@yativo.com.")
            ->line('Thank you for choosing '.getenv("APP_NAME").' for your financial needs.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            "title" => ucwords($this->type)." Notification",
            "message" => "Dear $notifiable->name,\n\n This is to notify you of a recent $this->type of $this->wallet $this->amount in your account.\n\nThank you,\n\n".getenv("APP_NAME")
        ];
    }
}
