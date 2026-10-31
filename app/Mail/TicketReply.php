<?php

namespace App\Mail;

use App\Models\TicketMessage;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * A teammate's reply, emailed to the merchant. The "[#id]" subject tag and
 * the stored Message-ID let the merchant's answer thread back to the ticket.
 */
class TicketReply extends Mailable
{
    public function __construct(public readonly TicketMessage $reply) {}

    public function envelope(): Envelope
    {
        $ticket = $this->reply->ticket;
        $support = config('support.address');

        return new Envelope(
            replyTo: $support ? [new Address($support, config('app.name'))] : [],
            subject: "Re: {$ticket->subject} {$ticket->mailTag()}",
        );
    }

    public function headers(): Headers
    {
        $references = $this->reply->ticket->messages()
            ->whereNotNull('email_message_id')
            ->where('id', '!=', $this->reply->id)
            ->pluck('email_message_id')
            ->all();

        return new Headers(messageId: $this->reply->email_message_id, references: $references);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.ticket-reply');
    }
}
