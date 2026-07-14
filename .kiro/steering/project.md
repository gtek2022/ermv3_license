# gemilang — AI Project Guide

> Central **license server** for the ERM platform. Read this before acting.

## What this is

gemilang issues, activates, revokes, and validates licenses for the client apps
(`ermv3`, `absensi`). It signs **PASETO v4 / Ed25519** tokens using a root key +
signing key hierarchy. It is the only holder of the private signing keys.

- Stack: **Laravel 13**, PHP 8.3, `masterix21/laravel-licensing`, hashids.
- Database: **PostgreSQL** (`DB_CONNECTION=pgsql`, db `gemilang`).
- Private keys live in `storage/app/licensing/keys` — never commit or move them.

## Hard boundary rule

gemilang **must not** become an operational CRUD server for ERM data. It only
serves the licensing endpoints under `/api/licensing/v1/`:

- `activate`, `deactivate`, `refresh`, `heartbeat`, `validate`
- `licenses/show`, `token`, `GET health`

It never reads from the `ermv3` database. Clients reach it only over HTTPS and
verify signatures with the embedded public key.

## Hard rules for this repo

1. **Never run destructive migrations** (`migrate:fresh|refresh|reset`,
   `db:wipe`). Use `php artisan make:migration` for schema changes.
2. Keep signing/verification logic PASETO v4 + Ed25519. Do not downgrade crypto
   or expose private keys via API/logs.
3. Prefer Eloquent + the package's own APIs over raw SQL.

## Common commands

```bash
php artisan migrate            # additive only
php artisan test               # Pest
vendor/bin/pint --dirty        # format PHP before finishing
bash prod-diagnose.sh          # licensing diagnostics helper
```

## Deploy

`python deploy.py` from this folder → pulls `origin/master` on the main VPS
(`/www/wwwroot/license.gemilangteknologi.com`) and rebuilds caches. Licensing
usage guide is in `docs/panduan-lisensi.html`.

## Related repos

- `ermv3` and `absensi` are license **clients**. Both call this server's
  `/api/licensing/v1/` endpoints and embed the public key.
