<?php

namespace App\Mail;

use App\Models\PredictionHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ScanCompleteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PredictionHistory $history) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hasil Scan Kulit Kamu Sudah Siap ✨');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.scan-complete');
    }
}
