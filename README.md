# Brix Partner Dashboard

One dashboard for every Brix Shopify app, fed by the Shopify Partner API:
installed stores, paying stores, client vs test stores, and what each store
earned, grouped into payout periods.

PHP 8.2+ (Laravel 12) and MySQL. Separate from `agency_dashboard`; nothing is shared.

## How it works

An hourly `partner:sync` pulls two feeds per app and stores them in MySQL:

| Feed | Partner API | Gives |
|---|---|---|
| App events | `app(id).events` | installs, uninstalls, deactivations, subscription charges (incl. `test` flag) |
| Transactions | `transactions(appId)` | subscription / one-time / usage sales, credits, adjustments — gross, Shopify fee, net |

`ShopRollup` then rebuilds each touched store: install status per app, current
plan, lifetime net, and its type:

- **Client**: has at least one real paid sale
- **Test**: only test charges (development stores never produce real transactions)
- **Unpaid**: installed but never paid (free plan, trial, or not yet billed)
- A manual override on the store page always wins.

**Payouts** are estimates. The Partner API returns transactions, not payouts, so
transactions are grouped by date into `PARTNER_PAYOUT_SCHEDULE` periods
(`semimonthly` by default). Check them against the Partner Dashboard.

## Support desk

**Tickets** come in from four places and all land in one list:

| Source | How |
|---|---|
| Team | **Tickets → New ticket**, for requests that came by phone, WhatsApp, etc. |
| Support form | Public page at `/support`. Link to it from each app as `/support?app=<Partner app ID>&shop=<shop>.myshopify.com` to prefill both. |
| Email | `tickets:fetch-mail` reads unread mail from the support mailbox every 5 minutes (`SUPPORT_IMAP_*`). Replies keep `[#123]` in the subject and thread back. |
| Chatbot | `POST /api/tickets` — see [REST API](#rest-api). |

The chatbot uses the REST API below (`POST /api/tickets`).

Team replies are emailed to the merchant (through `MAIL_*`, with Reply-To set to
`SUPPORT_MAIL_ADDRESS`); internal notes are not. A merchant writing back reopens the ticket.

**Setups**: each app has a setup checklist (**Apps → Setup checklist**, one step per line).
After every sync, each store that installed an app in the last `SETUPS_AUTO_DAYS` days
(test stores excluded) gets a setup with a copy of that checklist, to assign and tick off.
Older installs can be started by hand from the store page.

**Team**: admins see everything. Team members see tickets, setups and stores, but not
earnings, payouts, apps or settings. Add people under **Team**.

## REST API

Base URL `https://admin-partners.thebrix.io/api`. Every request needs
`Authorization: Bearer <SUPPORT_API_TOKEN>` (make one with `php artisan support:token`).
Send JSON. Responses are JSON wrapped in `data`; lists are paginated
(`?page=`, `?per_page=` up to 100) and add `links` and `meta`. Errors: 401 bad token,
404 not found, 422 validation (`{"message", "errors": {field: [...]}}`).
Earnings and plan prices are never returned.

| Method | Path | What it does |
|---|---|---|
| GET | `/tickets` | List. Filters: `status` (`open`, `pending`, `resolved`, `active`), `email`, `shop_domain`, `app`, `source`, `assignee_id`, `conversation_id` |
| POST | `/tickets` | Create (see below). Same `conversation_id` again → adds a message instead (200) |
| GET | `/tickets/{id}` | One ticket with its messages |
| PATCH | `/tickets/{id}` | Change any of `subject`, `status`, `priority`, `assignee_id`, `app`, `shop_domain`, `name`, `email` |
| DELETE | `/tickets/{id}` | Delete (204) |
| GET | `/tickets/{id}/messages` | Messages, oldest first |
| POST | `/tickets/{id}/messages` | Add a merchant message `{message, name?, email?}` — reopens the ticket |
| GET | `/setups` | List. Filters: `status` (`todo`, `in_progress`, `waiting`, `done`, `active`), `shop_domain`, `app`, `assignee_id` |
| POST | `/setups` | Start one: `{shop_domain, app, assignee_id?, due_on?, notes?}`. Copies the app's checklist; returns the existing setup (200) if there is one |
| GET | `/setups/{id}` | One setup with its tasks and progress |
| PATCH | `/setups/{id}` | Change any of `status`, `assignee_id`, `due_on` (YYYY-MM-DD), `notes` |
| DELETE | `/setups/{id}` | Delete (204) |
| POST | `/setups/{id}/tasks` | Add a step `{title}` |
| PATCH | `/setups/{id}/tasks/{taskId}` | `{title?, done?}` — `"done": true` ticks it off |
| DELETE | `/setups/{id}/tasks/{taskId}` | Remove a step (204) |
| GET | `/stores?q=` | Synced stores with installs; `GET /stores/{id or domain}` for one |
| GET | `/apps` | Apps (`id`, `partner_app_id`, `name`) |
| GET | `/users` | Team members, for `assignee_id` |

`app` accepts the app's name, its Partner app ID or its `id`. `shop_domain` accepts
`cool-shop`, `cool-shop.myshopify.com` or a full store URL. `priority` is `low`,
`normal`, `high` or `urgent`.

Create a ticket (only `message` is required):

```bash
curl -X POST https://admin-partners.thebrix.io/api/tickets \
  -H "Authorization: Bearer $SUPPORT_API_TOKEN" -H "Content-Type: application/json" \
  -d '{
    "message": "My discount is not applying at checkout",
    "subject": "Discount not applying",
    "name": "Asha",
    "email": "asha@example.com",
    "shop_domain": "cool-shop.myshopify.com",
    "app": "Cart Ninja",
    "priority": "normal",
    "conversation_id": "chat-8812"
  }'
```

Response (201 new ticket, 200 when added to an existing conversation):

```json
{
  "data": {
    "id": 42, "number": "#42", "subject": "Discount not applying",
    "status": "open", "priority": "normal", "source": "chatbot",
    "conversation_id": "chat-8812",
    "requester": {"name": "Asha", "email": "asha@example.com"},
    "shop_domain": "cool-shop.myshopify.com",
    "store": {"id": 7, "domain": "cool-shop.myshopify.com", "name": "Cool Shop", "type": "client"},
    "app": {"id": 1, "partner_app_id": "1234567", "name": "Cart Ninja"},
    "assignee": null,
    "messages": [{"id": 90, "from": "merchant", "author": {"name": "Asha", "email": "asha@example.com"},
                  "body": "My discount is not applying at checkout", "internal": false, "created_at": "…"}],
    "url": "https://admin-partners.thebrix.io/tickets/42",
    "last_activity_at": "…", "resolved_at": null, "created_at": "…", "updated_at": "…"
  },
  "created": true
}
```

## Setup

1. Create a MySQL database (e.g. `brix_partner_dashboard`).
2. `cp .env.example .env`, then fill in `DB_*`, `APP_URL`, and:
   - `SHOPIFY_PARTNER_ORG_ID`: the number in your Partner Dashboard URL
   - `SHOPIFY_PARTNER_API_TOKEN`: Dev Dashboard → Organization settings → Users → Security → Partner API clients. Grant **View apps** and **View financials**.
3. Install and migrate:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate
   php artisan migrate --force
   php artisan admin:create you@digifyce.com
   ```
4. Log in, open **Settings**, click **Test connection**, then add each app by its
   Partner app ID (the number in the app's Partner Dashboard URL).
5. First backfill, over SSH (can take a few minutes):
   ```bash
   php artisan partner:sync --full
   ```
6. Cron, every minute. In hPanel (Advanced → Cron Jobs, type PHP) point it at
   `cron.php` in the site root, e.g. `domains/<subdomain>/public_html/cron.php`.
   Anywhere with a normal crontab:
   ```
   * * * * * cd /path/to/partner_dashboard && php artisan schedule:run >> /dev/null 2>&1
   ```
   Either way, this runs an incremental `partner:sync` every hour.

## Hosting (same setup as agency_dashboard)

- The root `.htaccess` forwards every request into `public/`, so the repo can be
  deployed straight into `public_html`. `public/.htaccess` handles the rest.
- `public/build` is committed because the host has no Node. After changing anything in
  `resources/`, run `npm run build` locally and commit the result.
- `vendor/` is not committed. Run `composer install` on the server.

## Commands

| Command | What it does |
|---|---|
| `php artisan partner:sync` | Incremental sync (re-reads the last `PARTNER_SYNC_OVERLAP_DAYS`) |
| `php artisan partner:sync --full` | Re-reads everything since `PARTNER_BACKFILL_FROM` |
| `php artisan partner:payout-periods` | Regroups transactions after changing `PARTNER_PAYOUT_SCHEDULE` |
| `php artisan admin:create {email} [--role=team]` | Creates or resets a login (admin by default) |
| `php artisan tickets:fetch-mail` | Reads the support inbox now (also runs every 5 minutes) |
| `php artisan support:token` | Generates a `SUPPORT_API_TOKEN` for the chatbot |

## Tests

```bash
php artisan test
```

The tests use an in-memory SQLite database and a faked Partner API, so they
need no MySQL and no credentials.
