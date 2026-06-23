#!/bin/sh

set -eu

ROOT_DIR=$(git rev-parse --show-toplevel)
ENV_FILE="$ROOT_DIR/.env.license-expiration-investigation"
COMPOSE_FILE="$ROOT_DIR/dev.docker-compose.yml"
PROJECT_NAME="snipe-it-license-expiration-investigation"

if [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  . "$ENV_FILE"
  ENV_FILE_ARGS="--env-file $ENV_FILE"
else
  COMPOSE_PROJECT_NAME="$PROJECT_NAME"
  ENV_FILE_ARGS=""
fi

# shellcheck disable=SC2086
docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  $ENV_FILE_ARGS \
  -f "$COMPOSE_FILE" \
  down "$@"

cat <<EOF

Stopped the license expiration investigation environment.
Project: $COMPOSE_PROJECT_NAME
EOF
