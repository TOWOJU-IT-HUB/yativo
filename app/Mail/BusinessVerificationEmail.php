<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessVerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $name, $businessName, $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $businessName, $verificationUrl)
    {
        $this->name = $name;
        $this->businessName = $businessName;
        $this->verificationUrl = $verificationUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Business Verification Email');
    }

    /**
     * Get the message content definition.
     */
    public function build()
    {
        return $this->view('emails.business-kyc-verification')
            ->with([
                'name' => $this->name,
                'businessName' => $this->businessName,
                'verificationUrl' => $this->verificationUrl,
            ])
            ->subject('Please verify your KYC information');
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
