#!/bin/bash
set -e

# Filesystem-only, safe before the database exists.
php artisan config:clear

if [ "${SKIP_MIGRATIONS}" != "true" ]; then
    php artisan migrate --force --no-interaction

    # CACHE_STORE=database, so this must run after migrate or the
    # cache table does not exist yet on a first boot. Non-fatal: a
    # stale cache should not stop the container from starting.
    php artisan cache:clear || true

    php artisan optimize
    php artisan storage:link --force 2>/dev/null || true
fi

exec "$@"
