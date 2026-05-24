#!/bin/bash
# Read-only diagnose script for prod — does NOT modify anything.
# Usage: scp this to server, then bash prod-diagnose.sh
set -u

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✓${NC} $*"; }
fail() { echo -e "${RED}✗${NC} $*"; }
warn() { echo -e "${YELLOW}!${NC} $*"; }
section() { echo -e "\n${YELLOW}═══ $* ═══${NC}"; }

GEMILANG=/www/wwwroot/license.gemilangteknologi.com
ERMV3=/www/wwwroot/erm.gemilangteknologi.com

section "Server identity"
hostname
date
echo "Machine ID: $(cat /etc/machine-id 2>/dev/null || echo 'n/a')"

section "Gemilang ($GEMILANG)"
if [ -d "$GEMILANG" ]; then
    ok "Directory exists"
    cd "$GEMILANG" || exit 1
    echo "Git: $(git rev-parse --short HEAD 2>/dev/null) on $(git branch --show-current 2>/dev/null)"
    echo
    echo "--- DB info ---"
    php artisan tinker --execute="echo 'DB: ' . DB::connection()->getDatabaseName() . PHP_EOL . 'Driver: ' . DB::connection()->getDriverName() . PHP_EOL;" 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- LicensingKeys ---"
    php artisan licensing:keys:list 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- LicenseCompany rows ---"
    php artisan tinker --execute="App\Models\LicenseCompany::with('company')->get()->each(function(\$lc){echo \$lc->id.': '.\$lc->license_key.' | company='.(\$lc->company?->name ?? '-').' | status='.\$lc->status.' | hash='.substr(\$lc->license_key_hash,0,16).'...'.PHP_EOL;});" 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- Package License rows ---"
    php artisan tinker --execute="LucaLongo\Licensing\Models\License::all()->each(function(\$l){echo \$l->id.': hash='.substr(\$l->key_hash,0,16).'... | status='.\$l->status->value.PHP_EOL;});" 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- Public key endpoint test ---"
    curl -s "https://license.gemilangteknologi.com/api/platform/v1/public-key" | head -c 600
    echo
else
    fail "Gemilang directory not found"
fi

section "ERMv3 ($ERMV3)"
if [ -d "$ERMV3" ]; then
    ok "Directory exists"
    cd "$ERMV3" || exit 1
    echo "Git: $(git rev-parse --short HEAD 2>/dev/null) on $(git branch --show-current 2>/dev/null)"
    echo
    echo "--- .env DB config ---"
    grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME)=' .env
    echo
    echo "--- DB connection test (Laravel) ---"
    php artisan tinker --execute="try { echo 'DB: ' . DB::connection()->getDatabaseName() . ' | Driver: ' . DB::connection()->getDriverName() . ' | Connected: ' . (DB::connection()->getPdo() ? 'YES' : 'NO') . PHP_EOL; } catch (\Throwable \$e) { echo 'DB ERROR: ' . \$e->getMessage() . PHP_EOL; }" 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- Existing tables (pgsql) ---"
    php artisan tinker --execute="try { \$tables = collect(DB::select(\"SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename\"))->pluck('tablename'); echo 'Total: ' . \$tables->count() . PHP_EOL; echo \$tables->take(30)->implode(PHP_EOL); } catch (\Throwable \$e) { echo 'ERR: ' . \$e->getMessage() . PHP_EOL; }" 2>&1 | grep -v 'Module\|Warning'
    echo
    echo "--- Migration status ---"
    php artisan migrate:status 2>&1 | grep -v 'Module\|Warning' | head -30
    echo
    echo "--- License storage files ---"
    if [ -d "storage/app/licensing" ]; then
        ls -la storage/app/licensing/ 2>/dev/null
        echo
        if [ -d "storage/app/licensing/state" ]; then
            ls -la storage/app/licensing/state/ 2>/dev/null
        fi
    else
        warn "storage/app/licensing not found"
    fi
    echo
    echo "--- License diagnose command ---"
    if php artisan list 2>&1 | grep -q "license:diagnose"; then
        php artisan license:diagnose 2>&1 | grep -v 'Module\|Warning'
    else
        warn "license:diagnose command not yet present — run: git pull"
    fi
else
    fail "ERMv3 directory not found"
fi

section "Done — read-only, no changes were made"
