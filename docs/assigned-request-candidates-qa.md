# Assigned request candidates: implementation and local QA

## Behavior

The existing Received Requests inbox links to **Assigned — release required**. Candidate assets must match the exact catalog model, be checked out, have an operational (deployable, non-pending, non-archived) status, and not be deleted or RFQ-reserved. All checkout targets are supported. Project relationship comes from `assets.project_id`: Same project, Other project, or No project.

Assets expected back on or before Needed By remain exclusively in Due Back. With no Needed By, all eligible assigned assets appear in the new bucket. The count is a live inventory count, not a quantity capped to the request. Each request is evaluated independently.

Only an existing coordinator target or superuser with asset-list permission may use the bucket. Company/category visibility is retained, and counts use the same query as listings. Request ownership alone is insufficient. The listing has no mutation controls and returns all action flags as false. Normal checkout/transfer validation still rejects assigned assets.

Routing, reminders, requester views, procurement shortfall, and savings calculations are unchanged. An assigned-only match does not route a request to a new coordinator. No schema migration or historical backfill is needed.

Interfaces: the existing asset-list endpoint accepts `request_bucket=assigned_candidates` with `request_id`. Received Requests adds nullable `assigned_candidates_count` and `assigned_candidates_url`. Candidate rows add `project_relationship`. Unauthorized requests return 403; missing request context returns 404.

## Dedicated environment

Run commands from this worktree. The Compose project is `assigned-request-candidates`, with its own database, network, mail capture, storage, uploads, keys, and dependency volumes. It does not use the shared application's database. URLs bind to loopback only:

- App: http://localhost:8136
- Mail capture: http://localhost:8236

The current setup reuses the installed QA PHP runtime under a dedicated image tag; the source is bind-mounted from this branch. On another machine, build the runtime with `docker compose -f docker-compose.assigned-candidates.yml build app`. `SNIPEIT_QA_IMAGE` can select an equivalent local runtime. The network uses `10.251.36.0/24` because the host's default Docker address pools are exhausted; override `ASSIGNED_QA_SUBNET` if that subnet conflicts elsewhere.

```sh
docker compose -f docker-compose.assigned-candidates.yml up -d
docker compose -f docker-compose.assigned-candidates.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.assigned-candidates.yml exec -T app php artisan db:seed --class=LearAssignedCandidatesQaSeeder --force
```

Startup creates OAuth keys if missing and gives Apache ownership of keys and runtime storage. The seeder is repeatable, can run in any application environment, and uses synthetic electronics-lab data inspired by Lear. No real company records or personal data are used.

## Accounts and scenarios

All four local QA accounts use password `Lear-QA-2026!`:

| Username | Purpose |
| --- | --- |
| `le-qa-coordinator` | Received Requests and candidate review |
| `le-qa-admin` | Superuser review, including the assigned-only request |
| `le-qa-requester` | Owns requests; new candidate bucket must return 403 |
| `le-qa-outsider` | Has asset-list permission; new candidate bucket must return 403 |

FMCS is off in the demo so the coordinator can see the synthetic Rabat and Valls assets. Do not interpret that as cross-company authorization when FMCS is enabled. Automated tests cover company and category visibility separately.

| Scenario | Expected result |
| --- | --- |
| Open Received Requests as coordinator | One CAN-FD request, candidate count 6 |
| Follow the count | Six rows; no selection, bulk, checkout, transfer, or resolution controls |
| `LE-QA-SAME`, `LE-QA-OTHER`, `LE-QA-NO-PROJECT` | Same project, Other project, No project respectively |
| `LE-QA-LOCATION`, `LE-QA-NESTED` | Location/asset checkout targets displayed |
| `LE-QA-LATER` | Included, expected return later than Needed By |
| `LE-QA-DUE` | Only in Due Back, count 1 |
| `LE-QA-AVAILABLE` | Only in Reusable Now, count 1 |
| Reserved, repair, pending, archived, deleted tags | Excluded from assigned candidates |
| Search for an unmatched term | Clear candidate-specific empty message |
| Open ECU Debug Probe request as admin | One candidate; no routed coordinator |
| Open candidate URL as requester/outsider | 403 |

The seeder prints request IDs. In a new dedicated database the CAN-FD request is #1 and ECU Probe request #2. Needed By is 14 days after seeding; Due Back and Later dates are 7 and 30 days after seeding.

## Automated checks

Tests use a second database within this dedicated stack, never the seeded QA database. Create it once:

```sh
docker compose -f docker-compose.assigned-candidates.yml exec -T db mariadb -uroot -passigned-qa-root-local -e "CREATE DATABASE IF NOT EXISTS assigned_candidates_tests; GRANT ALL ON assigned_candidates_tests.* TO 'assigned_qa'@'%';"
cp .env.testing.example .env.testing
docker compose -f docker-compose.assigned-candidates.yml exec -T \
  -e APP_ENV=testing -e DB_DATABASE=assigned_candidates_tests \
  -e APP_CONFIG_CACHE=/tmp/assigned-candidates-test-config.php \
  -e CACHE_DRIVER=array -e SESSION_DRIVER=array -e MAIL_MAILER=array \
  app php vendor/bin/phpunit \
  tests/Feature/Requests/AssignedCandidatesTest.php \
  tests/Feature/Requests/ModelRequestWorkflowTest.php \
  tests/Feature/Requests/RacRequestInboxTest.php
```

The credentials and app key in this Compose file are local QA fixtures. Stop the environment without deleting its data using `docker compose -f docker-compose.assigned-candidates.yml down`.

## Verified result

The dedicated stack passed the three suites above: **86 tests, 512 assertions**. Browser verification as the seeded coordinator confirmed the Received Requests count of six, the linked candidate rows and project labels, the read-only controls, and the candidate-specific empty search message. Database readback confirmed 14 seeded assets, one coordinator target for the CAN-FD request, and no coordinator target for the assigned-only ECU Probe request.
