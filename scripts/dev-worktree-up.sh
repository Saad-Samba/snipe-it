#!/bin/sh

set -eu

ROOT_DIR=$(git rev-parse --show-toplevel)
WORKTREE_NAME=$(basename "$ROOT_DIR")
WORKTREE_ENV_FILE="$ROOT_DIR/.env.worktree"
COMPOSE_FILE="$ROOT_DIR/dev.docker-compose.yml"

slugify() {
  printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g; s/-\{2,\}/-/g; s/^-//; s/-$//'
}

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
  compose_project_name=$(slugify "$WORKTREE_NAME")
  app_port=$(find_free_port 8000)
  db_port=$(find_free_port 3306)
  mailhog_port=$(find_free_port 8025)
  cookie_name=$(slugify "${compose_project_name}-session")
  cache_prefix=$(slugify "$compose_project_name")

  cat >"$WORKTREE_ENV_FILE" <<EOF
COMPOSE_PROJECT_NAME=$compose_project_name
APP_PORT=$app_port
DB_PORT=$db_port
MAILHOG_PORT=$mailhog_port
APP_URL=http://localhost:$app_port
COOKIE_NAME=$cookie_name
CACHE_PREFIX=$cache_prefix
EOF
}

if [ ! -f "$WORKTREE_ENV_FILE" ]; then
  write_env_file
fi

# shellcheck disable=SC1090
. "$WORKTREE_ENV_FILE"

docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  --env-file "$WORKTREE_ENV_FILE" \
  -f "$COMPOSE_FILE" \
  up -d "$@"

cat <<EOF

Started worktree-local Snipe-IT stack.
Worktree: $WORKTREE_NAME
Project: $COMPOSE_PROJECT_NAME
App URL: $APP_URL
DB Port: $DB_PORT
MailHog URL: http://localhost:$MAILHOG_PORT
Env File: $WORKTREE_ENV_FILE
EOF
