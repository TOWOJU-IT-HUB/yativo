<?php

namespace Modules\SendMoney\app\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SendMoneyNotification extends Notification
{
    use Queueable;

    public $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->markdown('notifications.send_money', [
                'raw_data' => $this->data['raw_data'],
                'actionUrl' => getenv('WEB_URL'),
            ]);
    }


    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'send_amount' => $this->data['send_amount'],
            'transaction_fee' => $this->data['transaction_fee'],
            'estimated_delivery' => $this->data['estimated_delivery'],
            'send_currency' => $this->data['send_currency'],
            'beneficiary' => [
                'customer_name' => $this->data['beneficiary']['customer_name'],
                'customer_email' => $this->data['beneficiary']['customer_email'],
                'customer_address' => $this->data['beneficiary']['customer_address'],
            ],
        ];
    }
}
