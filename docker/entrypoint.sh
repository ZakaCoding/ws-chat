#!/bin/sh

set -eu

required_variables="APP_KEY REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET"

for variable_name in $required_variables; do
    eval "variable_value=\${$variable_name:-}"

    if [ -z "$variable_value" ]; then
        echo "$variable_name must be configured." >&2
        exit 1
    fi
done

install -d -o www-data -g www-data /data
install -d -o www-data -g www-data /app/storage/framework/cache/data
install -d -o www-data -g www-data /app/storage/framework/sessions
install -d -o www-data -g www-data /app/storage/framework/views
install -d -o www-data -g www-data /app/storage/logs
install -d -o www-data -g www-data /app/bootstrap/cache

database_path="${DB_DATABASE:-/data/database.sqlite}"

if [ ! -f "$database_path" ]; then
    touch "$database_path"
fi

chown www-data:www-data "$database_path"

runuser -u www-data -- php artisan migrate --force --no-interaction
runuser -u www-data -- php artisan optimize --no-interaction

exec "$@"
