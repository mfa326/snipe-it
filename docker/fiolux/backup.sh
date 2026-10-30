#!/bin/sh
set -eu
umask 077
case "$DB_DATABASE" in
    teste_fiolux_*) ;;
    *) echo 'Backup restrito ao banco de prova teste_fiolux_*.' >&2; exit 1 ;;
esac
stamp=$(date -u +%Y%m%dT%H%M%SZ)
target="/var/lib/snipeit/dumps/TESTE-FIOLUX-$stamp"
mkdir -p "$target"
export MYSQL_PWD="$DB_PASSWORD"
mariadb-dump --no-defaults --single-transaction --default-character-set=utf8mb4 --host="$DB_HOST" --user="$DB_USERNAME" "$DB_DATABASE" > "$target/database.sql"
tar -czf "$target/private-uploads.tgz" -C /var/lib/snipeit/data private_uploads
printf '%s\n' "$target"
