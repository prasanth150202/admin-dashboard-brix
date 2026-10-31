<?php

namespace App\Services\Support;

use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The one way tickets and messages get created, whatever the source
 * (team, support form, email, chatbot), so store matching and reopening
 * behave the same everywhere.
 */
class TicketIntake
{
    /**
     * @param  array{message: string, subject?: ?string, name?: ?string, email?: ?string, shop_domain?: ?string,
     *     app?: mixed, priority?: ?string, external_ref?: ?string, email_message_id?: ?string, assignee_id?: ?int}  $data
     */
    public function open(string $source, array $data): Ticket
    {
        $email = self::normalizeEmail($data['email'] ?? null);
        $domain = self::normalizeDomain($data['shop_domain'] ?? null);
        $shop = $domain ? Shop::where('myshopify_domain', $domain)->first() : null;

        // No store given: reuse the one from this merchant's earlier tickets.
        if (! $shop && ! $domain && $email) {
            $shop = Ticket::where('requester_email', $email)->whereNotNull('shop_id')->latest('id')->first()?->shop;
        }

        $subject = trim((string) ($data['subject'] ?? '')) ?: Str::limit(Str::squish(strtok($data['message'], "\n") ?: ''), 80);

        $ticket = Ticket::create([
            'subject' => Str::limit($subject ?: 'Support request', 250),
            'status' => 'open',
            'priority' => array_key_exists($data['priority'] ?? '', Ticket::PRIORITIES) ? $data['priority'] : 'normal',
            'source' => $source,
            'external_ref' => $data['external_ref'] ?? null,
            'shop_id' => $shop?->id,
            'shop_domain' => $domain ?? $shop?->myshopify_domain,
            'partner_app_id' => $this->resolveApp($data['app'] ?? null)?->id,
            'assignee_id' => $data['assignee_id'] ?? null,
            'requester_name' => $data['name'] ?? null,
            'requester_email' => $email,
            'last_activity_at' => now(),
        ]);

        $this->addMessage($ticket, $data['message'], name: $data['name'] ?? null, email: $email, messageId: $data['email_message_id'] ?? null);

        return $ticket;
    }

    /** A null $by means the merchant wrote it, which reopens a waiting or resolved ticket. */
    public function addMessage(
        Ticket $ticket,
        string $body,
        ?User $by = null,
        bool $internal = false,
        ?string $name = null,
        ?string $email = null,
        ?string $messageId = null,
    ): TicketMessage {
        $message = $ticket->messages()->create([
            'user_id' => $by?->id,
            'author_name' => $by?->name ?? $name ?? $ticket->requester_name,
            'author_email' => $by?->email ?? self::normalizeEmail($email) ?? $ticket->requester_email,
            'body' => trim($body),
            'is_internal' => $by !== null && $internal,
            'email_message_id' => $messageId,
        ]);

        $changes = ['last_activity_at' => now()];
        if ($by === null && $ticket->status !== 'open') {
            $changes += ['status' => 'open', 'resolved_at' => null];
        }
        $ticket->forceFill($changes)->save();

        return $message;
    }

    /** Matches an app by Partner app ID, local ID or (case-insensitive) name. */
    public function resolveApp(mixed $ref): ?PartnerApp
    {
        $ref = trim((string) $ref);

        if ($ref === '') {
            return null;
        }

        if (ctype_digit($ref)) {
            return PartnerApp::where('partner_app_id', $ref)->first() ?? PartnerApp::find((int) $ref);
        }

        return PartnerApp::whereRaw('LOWER(name) = ?', [Str::lower($ref)])->first();
    }

    /** "https://Cool-Shop.myshopify.com/admin" and "cool-shop" both become cool-shop.myshopify.com. */
    public static function normalizeDomain(?string $input): ?string
    {
        $domain = Str::lower(trim((string) $input));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = trim(explode('/', $domain)[0]);

        if ($domain === '' || ! preg_match('/^[a-z0-9][a-z0-9.-]*$/', $domain)) {
            return null;
        }

        return str_contains($domain, '.') ? $domain : "{$domain}.myshopify.com";
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
