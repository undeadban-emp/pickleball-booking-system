<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking not approved — {$this->booking->booking_code}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-rejected',
            with: [
                'booking' => $this->booking,
                'rebookUrl' => route('book.index'),
            ],
        );
    }
}
