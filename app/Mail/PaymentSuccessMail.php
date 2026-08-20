<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Subscription $subscription) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pembayaran Berhasil — SkinCek Pro Aktif 🎉');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.payment-success');
    }
}
