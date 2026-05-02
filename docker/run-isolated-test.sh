#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/dev.docker-compose.yml"

if [[ ! -f "${COMPOSE_FILE}" ]]; then
  echo "Missing compose file: ${COMPOSE_FILE}" >&2
  exit 1
fi

cd "${ROOT_DIR}"

docker compose -f "${COMPOSE_FILE}" run --rm --no-deps \
  --entrypoint /bin/sh \
  -e APP_ENV=testing \
  -e APP_CONFIG_CACHE=/tmp/codex-testing/config.php \
  -e APP_PACKAGES_CACHE=/tmp/codex-testing/packages.php \
  -e APP_SERVICES_CACHE=/tmp/codex-testing/services.php \
  -e APP_ROUTES_CACHE=/tmp/codex-testing/routes.php \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mariadb \
  -e DB_DATABASE=snipeittests \
  -e DB_USERNAME=snipeit \
  -e DB_PASSWORD=changeme1234 \
  -e CACHE_DRIVER=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_DRIVER=sync \
  -e VIEW_COMPILED_PATH=/tmp/codex-testing/views \
  -v "${ROOT_DIR}:/var/www/html" \
  snipeit -lc 'mkdir -p /tmp/codex-testing/views && exec php ./vendor/bin/phpunit -c phpunit.xml "$@"' sh "$@"
