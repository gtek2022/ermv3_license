# gemilang — AI Playbook (conventions, file map, recipes)

Use this to skip exploration and go straight to the right files/patterns.

## What lives where

| Need                       | Location                                        |
|----------------------------|-------------------------------------------------|
| API routes                 | `routes/api.php`                                |
| Controllers                | `app/Http/Controllers/` (+ `Api/`)              |
| Licensing services         | `app/Services/`                                 |
| Signing keys (secret)      | `storage/app/licensing/keys` (never commit)     |
| Models                     | `app/Models/`                                   |
| Migrations                 | `database/migrations/`                          |
| Diagnostics helper         | `prod-diagnose.sh`                              |

## Endpoint map (final URLs)

The `masterix21/laravel-licensing` package **self-registers** the core
endpoints under `/api/licensing/v1/*` (activate, deactivate, refresh,
heartbeat, validate, licenses/show, token, health). Custom routes in
`routes/api.php`:

- `POST /api/licensing/v1/policy`
- `GET  /api/platform/v1/public-key`      (no auth — clients fetch on install)
- `GET  /api/platform/v1/client-config`   (+ `/version` hash for polling)
- `POST /api/platform/v1/config-sync`
- `POST /api/platform/v1/feature/{activate|deactivate|status}`

## CRITICAL routing gotcha

Laravel's `bootstrap/app.php` (`withRouting`) already prefixes everything in
`routes/api.php` with `api/`. **Do NOT** add another `api/` prefix inside the
file, or URLs become `/api/api/...` and 404.

## Hard boundary

gemilang is **only** a license authority. Never add operational ERM CRUD here,
never read another app's database. Keep crypto at PASETO v4 + Ed25519; never
expose private keys via API, logs, or responses.

## Recipe: schema change

- `php artisan make:migration ...` (additive). Never `migrate:fresh|refresh|reset`, never `db:wipe`.

## Use Laravel Boost (MCP) first

Installed (`boost.json`). Use `search-docs`, `database-schema`, and `tinker`
(single quotes) before writing code.

## Verified commands

```bash
php artisan test --compact       # Pest
vendor/bin/pint --dirty          # format PHP before finishing
php artisan route:list --path=api/licensing
bash prod-diagnose.sh            # licensing diagnostics
```

## Gotchas

- DB is **PostgreSQL** (db `gemilang`).
- Encoding bridge: server emits standard base64, some clients expect base64url —
  keep token encoding consistent with the client verifier when touching signing.
