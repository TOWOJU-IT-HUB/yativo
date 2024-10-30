<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $magicLink, $magicPassword;

    /**
     * Create a new message instance.
     *
     * @param string $magicLink
     */
    public function __construct(string $magicLink, string $magicPassword)
    {
        $this->magicLink = $magicLink;
        $this->magicPassword = $magicPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.magic-link')
            ->subject('Your Magic Password')
            ->with([
                'magicLink' => $this->magicLink,
                'magicPassword' => $this->magicPassword,
            ]);
    }
}
