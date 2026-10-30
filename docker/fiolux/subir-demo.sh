#!/usr/bin/env bash
set -euo pipefail
umask 077
export MSYS_NO_PATHCONV=1
cd "$(dirname "${BASH_SOURCE[0]}")/../.."

url=''
bind=''
port=''
config_only=false
while [[ $# -gt 0 ]]; do
    case "$1" in
        --url) url="${2:?Informe a URL}"; shift 2 ;;
        --bind) bind="${2:?Informe o IP de escuta}"; shift 2 ;;
        --port) port="${2:?Informe a porta}"; shift 2 ;;
        --config-only) config_only=true; shift ;;
        *) echo 'Uso: bash docker/fiolux/subir-demo.sh [--url http://IP:3041] [--bind IP] [--port 3041] [--config-only]' >&2; exit 1 ;;
    esac
done
command -v docker >/dev/null || { echo 'Instale Docker Engine e o plugin Compose.' >&2; exit 1; }
docker compose version >/dev/null

if [[ -f .env ]]; then
    tr -d '\r' < .env | grep -qx 'DB_DATABASE=teste_fiolux_poc' && tr -d '\r' < .env | grep -qx 'DB_USERNAME=teste_fiolux' || { echo 'Configuração existente não pertence à demonstração. Use outra pasta; nenhum arquivo alterado.' >&2; exit 1; }
    configured_url=$(sed -n 's/^APP_URL=//p' .env | tr -d '\r' | head -n 1)
    [[ -z "$url" || "$url" = "$configured_url" ]] || { echo 'APP_URL existente é diferente. Ajuste a configuração local conscientemente; o script não a sobrescreve.' >&2; exit 1; }
    url="$configured_url"
    configured_port=$(sed -n 's/^FIOLUX_PORT=//p' .env | tr -d '\r' | head -n 1)
    port="${port:-${configured_port:-3041}}"
else
    port="${port:-3041}"
    url="${url:-http://localhost:$port}"
    [[ "$url" =~ ^https?://[a-zA-Z0-9.:-]+$ ]] || { echo 'Use uma URL HTTP/HTTPS sem caminho ou credenciais.' >&2; exit 1; }
    if [[ -z "$bind" ]]; then
        case "$url" in http://localhost*|http://127.0.0.1*|https://localhost*|https://127.0.0.1*) bind=127.0.0.1 ;; *) bind=0.0.0.0 ;; esac
    fi
    base_image='snipe/snipe-it:v8.8.3@sha256:eb6d78c1bdae46d15177b149fcc14d7cd795d9f6c17e53dc4d87e04da9653218'
    env_tmp=$(mktemp .env.fiolux.XXXXXX)
    trap 'if [[ -n "${env_tmp:-}" ]]; then rm -f -- "$env_tmp"; fi' EXIT
    docker run --rm --network none --entrypoint php \
        -e FIOLUX_PUBLIC_URL="$url" -e FIOLUX_LISTEN="$bind" -e FIOLUX_LISTEN_PORT="$port" "$base_image" -r '
        $values = ["APP_ENV"=>"production", "APP_DEBUG"=>"false", "APP_KEY"=>"base64:".base64_encode(random_bytes(32)),
          "APP_URL"=>getenv("FIOLUX_PUBLIC_URL"), "APP_TIMEZONE"=>"America/Sao_Paulo", "APP_LOCALE"=>"pt-BR",
          "DB_CONNECTION"=>"mysql", "DB_HOST"=>"db", "DB_PORT"=>"3306", "DB_DATABASE"=>"teste_fiolux_poc", "DB_USERNAME"=>"teste_fiolux",
          "DB_PASSWORD"=>bin2hex(random_bytes(24)), "MYSQL_ROOT_PASSWORD"=>bin2hex(random_bytes(24)),
          "MAIL_MAILER"=>"array", "MAIL_DRIVER"=>"array", "MAIL_FROM_ADDR"=>"teste-fiolux@example.invalid", "MAIL_FROM_NAME"=>"\"TESTE FIOLUX\"",
          "QUEUE_CONNECTION"=>"sync", "SESSION_DRIVER"=>"file", "CACHE_DRIVER"=>"file", "ALLOW_BACKUP_DELETE"=>"false",
          "FIOLUX_ENABLED"=>"true", "FIOLUX_DEMO_PASSWORD"=>bin2hex(random_bytes(18)), "FIOLUX_BIND"=>getenv("FIOLUX_LISTEN"), "FIOLUX_PORT"=>getenv("FIOLUX_LISTEN_PORT")];
        foreach ($values as $key=>$value) { echo $key."=".$value.PHP_EOL; }
        ' > "$env_tmp"
    mv -- "$env_tmp" .env
    env_tmp=''
fi
tr -d '\r' < .env | grep -qx 'MAIL_MAILER=array' && tr -d '\r' < .env | grep -qx 'MAIL_DRIVER=array' && tr -d '\r' < .env | grep -qx 'FIOLUX_ENABLED=true' || { echo 'Exige Fiolux habilitado e mailer array.' >&2; exit 1; }
chmod 600 .env
[[ "$port" =~ ^[1-9][0-9]{0,4}$ ]] && ((port < 65536)) || { echo 'Porta inválida.' >&2; exit 1; }
if "$config_only"; then printf 'Configuração privada criada ou preservada. APP_URL=%s\n' "$url"; exit 0; fi
export FIOLUX_PORT="$port"
if [[ -n "$bind" ]]; then export FIOLUX_BIND="$bind"; fi
export FIOLUX_REVISION
FIOLUX_REVISION=$(git rev-parse HEAD 2>/dev/null || printf 'working-tree')
if ! git diff --quiet HEAD -- app config database routes resources Dockerfile.fiolux docker/fiolux 2>/dev/null; then FIOLUX_REVISION="$FIOLUX_REVISION-dirty"; fi
docker compose -p teste-fiolux-snipeit -f compose.fiolux.yml config --quiet
docker compose -p teste-fiolux-snipeit -f compose.fiolux.yml up -d --build --wait --wait-timeout 300
docker compose -p teste-fiolux-snipeit -f compose.fiolux.yml exec -T --user docker app php artisan fiolux:meeting --no-interaction
printf '\nAmbiente: %s/fiolux-demo/\nRevisão: %s\nSenha das contas fictícias: FIOLUX_DEMO_PASSWORD no .env local (não publicar).\n' "$url" "$FIOLUX_REVISION"
