<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\Http;

/**
 * Thin GraphQL client for partners.shopify.com/{org}/api/{version}/graphql.json.
 *
 * Shopify allows 4 requests/second per Partner API client; this spaces
 * requests out to stay under that and retries throttled ones (a 429, or a
 * 200 carrying a "Too many requests" error) with backoff.
 */
class PartnerClient
{
    private const MIN_INTERVAL_MS = 300;

    private const MAX_RETRIES = 5;

    private float $lastRequestAt = 0;

    public function __construct(
        private readonly ?string $orgId,
        private readonly ?string $token,
        private readonly string $version,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('partner.org_id'), config('partner.api_token'), config('partner.api_version'));
    }

    public function isConfigured(): bool
    {
        return filled($this->orgId) && filled($this->token);
    }

    /** @throws PartnerApiException */
    public function query(string $query, array $variables = []): array
    {
        if (! $this->isConfigured()) {
            throw new PartnerApiException('Partner API is not configured — set SHOPIFY_PARTNER_ORG_ID and SHOPIFY_PARTNER_API_TOKEN in .env.');
        }

        $endpoint = "https://partners.shopify.com/{$this->orgId}/api/{$this->version}/graphql.json";

        for ($attempt = 0; ; $attempt++) {
            $this->throttle();

            $response = Http::withHeaders(['X-Shopify-Access-Token' => $this->token])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post($endpoint, ['query' => $query, 'variables' => (object) $variables]);

            $errors = $response->json('errors') ?? [];
            $throttled = $response->status() === 429
                || collect($errors)->contains(fn ($e) => str_contains(strtolower($e['message'] ?? ''), 'too many requests'));

            if ($throttled && $attempt < self::MAX_RETRIES) {
                usleep((int) (1_000_000 * 2 ** $attempt));

                continue;
            }

            if ($response->status() === 401 || $response->status() === 403) {
                throw new PartnerApiException('Partner API rejected the token (HTTP '.$response->status().'). Check the token and that the client has "View apps" and "View financials".');
            }

            if (! $response->successful()) {
                throw new PartnerApiException('Partner API returned HTTP '.$response->status().': '.mb_strimwidth($response->body(), 0, 300, '…'));
            }

            if ($errors !== []) {
                throw new PartnerApiException('Partner API error: '.collect($errors)->pluck('message')->implode('; '));
            }

            return $response->json('data') ?? [];
        }
    }

    private function throttle(): void
    {
        $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;

        if ($elapsedMs < self::MIN_INTERVAL_MS) {
            usleep((int) ((self::MIN_INTERVAL_MS - $elapsedMs) * 1000));
        }

        $this->lastRequestAt = microtime(true);
    }
}
