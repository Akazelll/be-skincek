<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MessageNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $snippet,
        public readonly string $conversationId,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pesan Baru dari '.$this->senderName.' 💬');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.message-notification');
    }
}
