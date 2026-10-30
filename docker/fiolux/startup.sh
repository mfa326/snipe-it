#!/bin/sh
set -eu

case "${DB_DATABASE:-}" in teste_fiolux_*) ;; *) echo 'Banco deve ser exclusivo da demonstração teste_fiolux_*.' >&2; exit 1 ;; esac
[ "${FIOLUX_ENABLED:-}" = true ] && [ "${MAIL_MAILER:-}" = array ] && [ "${MAIL_DRIVER:-}" = array ] || { echo 'Exige recurso Fiolux e notificações externas desativadas.' >&2; exit 1; }

mkdir -p /var/lib/snipeit/data/private_uploads/fiolux /var/lib/snipeit/data/uploads /var/lib/snipeit/dumps /var/lib/snipeit/keys
php artisan config:clear
php artisan migrate --force --no-interaction
php artisan view:clear
if [ ! -s storage/oauth-private.key ] || [ ! -s storage/oauth-public.key ]; then
    php artisan passport:keys --no-interaction
fi
if [ "${FIOLUX_PREPARE_DEMO:-false}" = true ]; then
    php artisan fiolux:prepare --demonstrate --no-interaction
    php artisan fiolux:meeting --no-interaction
fi
php artisan config:cache
php artisan view:cache
chown -R docker:staff /var/lib/snipeit storage bootstrap/cache
a2dissite 001-default-ssl >/dev/null
exec apache2ctl -D FOREGROUND
