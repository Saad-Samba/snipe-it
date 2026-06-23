#!/bin/sh

set -eu

ROOT_DIR=$(git rev-parse --show-toplevel)
ENV_FILE="$ROOT_DIR/.env.license-expiration-investigation"
COMPOSE_FILE="$ROOT_DIR/dev.docker-compose.yml"
PROJECT_NAME="snipe-it-license-expiration-investigation"

port_in_use() {
  port="$1"
  if command -v lsof >/dev/null 2>&1; then
    lsof -iTCP:"$port" -sTCP:LISTEN -n -P >/dev/null 2>&1
    return $?
  fi

  if command -v nc >/dev/null 2>&1; then
    nc -z 127.0.0.1 "$port" >/dev/null 2>&1
    return $?
  fi

  return 1
}

find_free_port() {
  port="$1"

  while port_in_use "$port"; do
    port=$((port + 1))
  done

  printf '%s\n' "$port"
}

write_env_file() {
  app_port=$(find_free_port 8100)
  db_port=$(find_free_port 3406)
  mailhog_port=$(find_free_port 8125)

  cat >"$ENV_FILE" <<EOF
COMPOSE_PROJECT_NAME=$PROJECT_NAME
APP_PORT=$app_port
DB_PORT=$db_port
MAILHOG_PORT=$mailhog_port
APP_URL=http://localhost:$app_port
COOKIE_NAME=${PROJECT_NAME}_session
CACHE_PREFIX=$PROJECT_NAME
EOF
}

if [ ! -f "$ENV_FILE" ]; then
  write_env_file
fi

# shellcheck disable=SC1090
. "$ENV_FILE"

docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  --env-file "$ENV_FILE" \
  -f "$COMPOSE_FILE" \
  up -d --build "$@"

attempt=0
until docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  --env-file "$ENV_FILE" \
  -f "$COMPOSE_FILE" \
  exec -T snipeit php artisan --version >/dev/null 2>&1
do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 30 ]; then
    echo "The investigation container did not become ready in time." >&2
    exit 1
  fi
  sleep 2
done

docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  --env-file "$ENV_FILE" \
  -f "$COMPOSE_FILE" \
  exec -T snipeit php artisan db:seed --class=ManualLicenseExpirationInvestigationSeeder

cat <<EOF

Started the license expiration investigation environment.
Project: $COMPOSE_PROJECT_NAME
App URL: $APP_URL
DB Port: $DB_PORT
MailHog URL: http://localhost:$MAILHOG_PORT
Env File: $ENV_FILE

Login:
  username: qa-license-admin
  password: password

Seeded licenses:
  QA Perpetual CAD Suite
  QA Annual Design Cloud
  QA Expired Security Scanner
  QA Contractor Tooling Bundle
  QA Vendor Managed Analytics
EOF
