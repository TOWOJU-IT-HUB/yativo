<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendMoneyMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.send_money')
            ->subject('Send Money Transaction Initiated')
            ->with([
                'send_amount' => $this->data['send_amount'],
                'transaction_fee' => $this->data['transaction_fee'],
                'estimated_delivery' => $this->data['estimated_delivery'],
                'send_currency' => $this->data['send_currency'],
                'beneficiary' => $this->data['beneficiary'],
                'payment_info' => $this->data['payment_info'],
                'actionUrl' => getenv('WEB_URL') . '/dashboard',
            ]);
    }
}
