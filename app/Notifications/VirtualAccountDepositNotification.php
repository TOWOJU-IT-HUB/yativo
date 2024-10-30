<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VirtualAccountDepositNotification extends Notification
{
    use Queueable;
    public $transaction;

    /**
     * Create a new notification instance.
     */
    public function __construct($payload)
    {
        $this->transaction = $payload;
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
            ->subject('Virtual Account Deposit Notification')
            ->view('emails.virtual_account_deposit_notification', [
                'transactionType' => $this->transaction['transactionType'],
                'externalId' => $this->transaction['externalId'],
                'amount' => $this->transaction['amount'],
                'currency' => $this->transaction['currency'],
                'statusDescription' => $this->transaction['status']['description'],
                'beneficiaryName' => $this->transaction['beneficiary']['name'],
                'payerName' => $this->transaction['payer']['name'],
                'referenceCode' => $this->transaction['referenceCode'],
                'creationDate' => $this->transaction['date']['creationDate'],
                'actionUrl' => getenv('WEB_URL')
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            "title" => "Virtual Account Deposit",
            "message" => "You have received a deposit of {$this->transaction['currency']} {$this->transaction['amount']} from {$this->transaction['beneficiary']['name']}."
        ];
    }
}
