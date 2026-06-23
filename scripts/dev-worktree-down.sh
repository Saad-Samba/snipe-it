#!/bin/sh

set -eu

ROOT_DIR=$(git rev-parse --show-toplevel)
WORKTREE_NAME=$(basename "$ROOT_DIR")
WORKTREE_ENV_FILE="$ROOT_DIR/.env.worktree"
COMPOSE_FILE="$ROOT_DIR/dev.docker-compose.yml"

slugify() {
  printf '%s' "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g; s/-\{2,\}/-/g; s/^-//; s/-$//'
}

if [ -f "$WORKTREE_ENV_FILE" ]; then
  # shellcheck disable=SC1090
  . "$WORKTREE_ENV_FILE"
  ENV_FILE_ARGS="--env-file $WORKTREE_ENV_FILE"
else
  COMPOSE_PROJECT_NAME=$(slugify "$WORKTREE_NAME")
  ENV_FILE_ARGS=""
fi

# shellcheck disable=SC2086
docker compose \
  --project-name "$COMPOSE_PROJECT_NAME" \
  $ENV_FILE_ARGS \
  -f "$COMPOSE_FILE" \
  down "$@"

cat <<EOF

Stopped worktree-local Snipe-IT stack.
Worktree: $WORKTREE_NAME
Project: $COMPOSE_PROJECT_NAME
EOF
