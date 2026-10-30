#!/bin/sh
set -eu

mkdir -p /var/lib/snipeit/data/private_uploads/fiolux /var/lib/snipeit/data/uploads /var/lib/snipeit/dumps /var/lib/snipeit/keys
php artisan config:clear
APP_ENV=testing php artisan migrate --force --no-interaction
php artisan view:clear
if [ ! -s storage/oauth-private.key ] || [ ! -s storage/oauth-public.key ]; then
    php artisan passport:keys --no-interaction
fi
chown -R docker:staff /var/lib/snipeit storage bootstrap/cache
a2dissite 001-default-ssl >/dev/null
exec apache2ctl -D FOREGROUND
