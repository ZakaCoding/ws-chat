# Support Chat Backend

Minimal Laravel backend for durable guest-to-operator conversations. SQLite is the source of truth; Laravel Reverb only delivers committed messages in real time.

## Local setup

Requirements: PHP 8.3+, Composer, the PHP SQLite extension, and a supported Laravel Octane server.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan octane:install --server=frankenphp
```

The example environment uses SQLite at `database/database.sqlite`, Octane with FrankenPHP, Reverb on port 8080, and placeholder Reverb credentials. Replace `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` outside local development. Set `REVERB_ALLOWED_ORIGINS` to the frontend origins allowed to connect.

Start each long-running process in its own terminal:

```bash
php artisan octane:start --server=frankenphp
php artisan reverb:start
php artisan queue:work --tries=3
```

The queue worker is required because `MessageCreated` uses Laravel's queued broadcasting path. Run `php artisan octane:reload` after a production deployment so long-lived workers load the new code.

## API

Create a guest conversation:

```bash
curl -X POST http://127.0.0.1:8000/api/conversations \
  -H 'Accept: application/json'
```

Save the returned `data.id` and `data.token`. The token is returned once.

Restore the conversation and message history:

```bash
curl http://127.0.0.1:8000/api/conversations/CONVERSATION_ID \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer GUEST_TOKEN'
```

Send a guest message:

```bash
curl -X POST http://127.0.0.1:8000/api/conversations/CONVERSATION_ID/messages \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer GUEST_TOKEN' \
  -d '{"body":"Hello"}'
```

Operator routes are under `/api/operator/conversations` and use Laravel's existing authenticated `web` user session. No operator login flow is included.

## Realtime contract

Authorize private subscriptions at `POST /broadcasting/auth`. Guests send the same bearer token used by the REST API; operators use their authenticated Laravel session.

- Private channel: `private-conversation.{conversationId}`
- Laravel/Echo channel name: `conversation.{conversationId}`
- Event name: `message.created`

The event data is exactly the REST message resource:

```json
{
  "id": "...",
  "conversation_id": "...",
  "sender": {
    "type": "guest",
    "name": null
  },
  "body": "Hello",
  "created_at": "..."
}
```

Guest credentials contain 256 bits of random entropy and are encoded as URL-safe base64. Only a SHA-256 digest is stored. Every lookup includes both the requested conversation and token digest, and the token is never included in message or event payloads.

## Tests

```bash
php artisan test --compact
```

## Fly.io deployment

The included `Dockerfile` and `fly.toml` run Octane, Reverb, and the database queue worker in one supervised Fly Machine. Caddy routes `/app/*` and `/apps/*` to Reverb and all other traffic to Laravel. SQLite lives at `/data/database.sqlite` on the encrypted `ws_chat_data` volume; automatic daily snapshots are retained for 14 days.

The deployment intentionally stays at one Machine because a Fly volume attaches to one Machine and this application uses SQLite. Keep `auto_stop_machines = "off"` so WebSocket connections and queued broadcasts remain available.

Install and authenticate the Fly CLI, then create and deploy the application:

```bash
fly auth login
fly apps create ws-chat-zakacoding
fly volumes create ws_chat_data --region sin --size 1 --snapshot-retention 14 -a ws-chat-zakacoding

fly secrets set -a ws-chat-zakacoding \
  APP_KEY="$(php artisan key:generate --show)" \
  REVERB_APP_ID="$(php -r 'echo bin2hex(random_bytes(16));')" \
  REVERB_APP_KEY="$(php -r 'echo bin2hex(random_bytes(16));')" \
  REVERB_APP_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')"

fly deploy -a ws-chat-zakacoding
fly status -a ws-chat-zakacoding
```

The container creates the SQLite file, runs `php artisan migrate --force`, caches Laravel configuration and routes, and then starts all three services. Configure `REVERB_ALLOWED_ORIGINS` with `fly secrets set` when the production frontend origin is known.
