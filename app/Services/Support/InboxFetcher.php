<?php

namespace App\Services\Support;

use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * Reads unread mail from the support mailbox and hands each one to
 * MailIngest. A message is only marked read after it has been stored, so
 * a crash halfway leaves the rest for the next run.
 */
class InboxFetcher
{
    public function __construct(private readonly MailIngest $ingest) {}

    public static function isConfigured(): bool
    {
        return filled(config('support.imap.host')) && filled(config('support.imap.username')) && filled(config('support.imap.password'));
    }

    /** @return array{read: int, tickets: int, errors: list<string>} */
    public function fetch(int $limit = 50): array
    {
        $client = (new ClientManager)->make([
            'host' => config('support.imap.host'),
            'port' => config('support.imap.port'),
            'encryption' => config('support.imap.encryption'),
            'validate_cert' => true,
            'username' => config('support.imap.username'),
            'password' => config('support.imap.password'),
            'protocol' => 'imap',
        ]);
        $client->connect();

        $result = ['read' => 0, 'tickets' => 0, 'errors' => []];

        try {
            $messages = $client->getFolderByPath(config('support.imap.folder'))
                ->query()->whereUnseen()->leaveUnread()->setFetchOrderAsc()->limit($limit)->get();

            foreach ($messages as $message) {
                try {
                    $ticket = $this->ingest->ingest($this->normalize($message));
                    $message->setFlag('Seen');
                    $result['read']++;
                    $result['tickets'] += $ticket ? 1 : 0;
                } catch (Throwable $e) {
                    report($e);
                    $result['errors'][] = $e->getMessage();
                }
            }
        } finally {
            $client->disconnect();
        }

        return $result;
    }

    private function normalize(Message $message): array
    {
        $from = $message->getFrom()->first();
        $body = $message->hasTextBody()
            ? $message->getTextBody()
            : html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $message->getHTMLBody())));

        return [
            'message_id' => (string) $message->getMessageId(),
            'from_email' => $from?->mail,
            'from_name' => $from?->personal ?: null,
            'subject' => (string) $message->getSubject(),
            'body' => $body,
            'in_reply_to' => (string) $message->getInReplyTo(),
            'references' => (string) $message->getReferences(),
        ];
    }
}
