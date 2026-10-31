<?php

namespace App\Services\Support;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Str;

/**
 * Turns one inbound email into a ticket or a reply on an existing one.
 * Kept apart from the IMAP fetch so it can be tested with plain arrays.
 */
class MailIngest
{
    public function __construct(private readonly TicketIntake $intake) {}

    /**
     * @param  array{message_id: ?string, from_email: ?string, from_name: ?string, subject: ?string, body: string,
     *     in_reply_to?: ?string, references?: ?string}  $mail
     * @return Ticket|null the ticket it landed on, or null when skipped
     */
    public function ingest(array $mail): ?Ticket
    {
        $messageId = self::cleanId($mail['message_id'] ?? null);
        $from = TicketIntake::normalizeEmail($mail['from_email'] ?? null);

        if ($messageId && TicketMessage::where('email_message_id', $messageId)->exists()) {
            return null;
        }

        // Never ticket our own outgoing mail (bounces, CC loops).
        if ($from === null || $from === TicketIntake::normalizeEmail(config('support.address'))) {
            return null;
        }

        $body = self::stripQuoted($mail['body']);
        if ($body === '') {
            $body = '(empty message)';
        }

        if ($ticket = $this->findThread($mail)) {
            $this->intake->addMessage($ticket, $body, name: $mail['from_name'] ?? null, email: $from, messageId: $messageId);

            return $ticket;
        }

        return $this->intake->open('email', [
            'subject' => preg_replace('/^\s*((re|fwd?|aw)\s*:\s*)+/i', '', (string) ($mail['subject'] ?? '')),
            'message' => $body,
            'name' => $mail['from_name'] ?? null,
            'email' => $from,
            'shop_domain' => preg_match('/\b([a-z0-9][a-z0-9-]*\.myshopify\.com)\b/i', $body, $m) ? $m[1] : null,
            'email_message_id' => $messageId,
        ]);
    }

    /** "[#123]" in the subject wins; otherwise In-Reply-To / References pointing at a message we sent. */
    private function findThread(array $mail): ?Ticket
    {
        if (preg_match('/\[#(\d+)\]/', (string) ($mail['subject'] ?? ''), $m) && $ticket = Ticket::find((int) $m[1])) {
            return $ticket;
        }

        preg_match_all('/<([^>]+)>/', ($mail['in_reply_to'] ?? '').' '.($mail['references'] ?? ''), $ids);

        if ($ids[1] === []) {
            return null;
        }

        return TicketMessage::whereIn('email_message_id', $ids[1])->latest('id')->first()?->ticket;
    }

    /** Drops the quoted history most mail clients append under a reply. */
    public static function stripQuoted(string $body): string
    {
        $lines = preg_split('/\R/', str_replace("\r\n", "\n", $body));
        $kept = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*(On .+wrote:|-{2,}\s*Original Message|From: .+@.+)\s*$/i', $line) || str_starts_with(ltrim($line), '>')) {
                break;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    public static function cleanId(?string $id): ?string
    {
        $id = trim((string) $id, " \t\n\r<>");

        return $id === '' ? null : Str::limit($id, 250, '');
    }
}
