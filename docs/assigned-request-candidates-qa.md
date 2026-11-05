# Assigned request candidates: implementation and local QA

## Behavior

The existing Received Requests inbox links to **In Use**. Candidate assets must match the exact catalog model, be checked out, have an operational (deployable, non-pending, non-archived) status, and not be deleted or RFQ-reserved. All checkout targets are supported. Project relationship comes from `assets.project_id`: Same project, Other project, or Project Missing.

Assets expected back on or before Needed By remain exclusively in Due Back. With no Needed By, all eligible assigned assets appear in the new bucket. The count is a live inventory count, not a quantity capped to the request. Each request is evaluated independently.

Only an existing coordinator target or superuser with asset-list permission may use the bucket. Company/category visibility is retained, and counts use the same query as listings. Request ownership alone is insufficient. The listing has no mutation controls and returns all action flags as false. Normal checkout/transfer validation still rejects assigned assets.

Routing, reminders, requester views, procurement shortfall, and savings calculations are unchanged. An assigned-only match does not route a request to a new coordinator. No schema migration or historical backfill is needed.

Interfaces: the existing asset-list endpoint accepts `request_bucket=assigned_candidates` with `request_id`. Received Requests adds nullable `assigned_candidates_count` and `assigned_candidates_url`. Candidate rows add `project_relationship`. Unauthorized requests return 403; missing request context returns 404.

## Requester catalogue and demand capture

The requester catalogue at http://localhost:8136/account/requestable-assets shows all non-deleted models to users with `models.request`, regardless of available stock, current assignments, or inventory company. The previous stock-based `models.request.all_companies` distinction no longer limits this catalogue. Asset visibility rules still apply to post-submission asset drilldowns.

The catalogue has server-rendered pages of 25 models. Search uses the standard table toolbar, with the category filter and bulk-add control alongside it. Search queries the whole catalogue and remains usable when no rows match. Technical search offers field names, numeric comparisons (`>=`, `<=`, `=`), allowed-value suggestions, and removable filter chips. Filters apply to the entire catalogue before pagination and can be combined with text and category searches. Model identifiers and requestable, non-encrypted technical defaults remain available. Obsolete models are labeled and remain requestable. No availability or reference-price columns are shown.

The cart shows model, discipline, destination site, requested quantity, project, and Needed By. Its preview endpoint returns only demand metadata and requested totals, without sourcing calculations. Existing submission validation, estimation snapshots, routing, allocation behavior, and post-submission reports remain in place. Submission alone does not allocate assets or approve procurement.

For a manual check, log in as `le-qa-requester`, add four **SM660-AR-11** units to the cart, select the project and Needed By, and submit. This model has no physical assets. Verify that the catalogue/cart expose no stock counts or sourcing estimates; after submission, the request retains quantity four and the specified context, with an estimated shortfall of four and no allocated assets. A browser-verified sample is already recorded as request #3 in the current QA database. Repeating that exact active model/project/site/discipline request will encounter the existing duplicate protection; use a different project for a new manual submission.

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

This branch incorporates `feature/reuse-workflow` through `032b0ff59a`. After pulling this update into an existing Docker environment, run migrations before testing; rebuild the app if your environment copies source into its image. Rerun the seeder to add the new synthetic technical specifications.

Startup creates OAuth keys if missing and gives Apache ownership of keys and runtime storage. The seeder is repeatable, can run in any application environment, and uses synthetic electronics-lab data inspired by Lear. No real company records or personal data are used.

## Accounts and scenarios

The demo administrator uses `admin` / `password`. The other three QA accounts use password `Lear-QA-2026!`:

| Username | Purpose |
| --- | --- |
| `le-qa-coordinator` | Received Requests and candidate review |
| `admin` | Superuser review, including the assigned-only request |
| `le-qa-requester` | Owns requests; new candidate bucket must return 403 |
| `le-qa-outsider` | Has asset-list permission; new candidate bucket must return 403 |

FMCS is off in the demo so the coordinator can see the synthetic IEC and VLS assets. Do not interpret that as cross-company authorization when FMCS is enabled. Automated tests cover company and category visibility separately.

| Scenario | Expected result |
| --- | --- |
| Open Received Requests as coordinator | One CAN-FD request, candidate count 6 |
| Follow the count | Six rows; no selection, bulk, checkout, transfer, or resolution controls |
| `LE-QA-SAME`, `LE-QA-OTHER`, `LE-QA-NO-PROJECT` | Other project, Other project, Project Missing respectively |
| `LE-QA-LOCATION`, `LE-QA-NESTED` | Location/asset checkout targets displayed |
| `LE-QA-LATER` | Included, expected return later than Needed By |
| `LE-QA-DUE` | Only in Due Back, count 1 |
| `LE-QA-AVAILABLE` | Only in Reusable Now, count 1 |
| Reserved, repair, pending, archived, deleted tags | Excluded from assigned candidates |
| Search for an unmatched term | Clear candidate-specific empty message |
| Open ECU Debug Probe request as admin | Two candidates; no routed coordinator |
| Open candidate URL as requester/outsider | 403 |

The seeder prints request IDs. In a new dedicated database the CAN-FD request is #1 and ECU Probe request #2. Needed By is 14 days after seeding; Due Back and Later dates are 7 and 30 days after seeding.

### Technical-search walkthrough

As `le-qa-requester`, open Requestable Models and type **QA Supply Voltage** in the standard table search. Choose `>=`, enter `20`, and press Enter. Only **SM660-AR-11** (60) should remain. Add **QA Interface Type: DC** using the suggested value; both chips should remain active. Read the inline specification badges to inspect the model values. Change category and verify both filters remain applied; remove a chip to broaden results. The other seeded models have voltage/interface values 12/CAN-FD and 5/JTAG.

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
  tests/Feature/Requests/RequestableModelsPageTest.php \
  tests/Feature/Requests/AssignedCandidatesTest.php \
  tests/Feature/Requests/ModelRequestWorkflowTest.php \
  tests/Feature/Requests/RacRequestInboxTest.php
```

The credentials and app key in this Compose file are local QA fixtures. Stop the environment without deleting its data using `docker compose -f docker-compose.assigned-candidates.yml down`.

## Verified result

After merging the reuse-workflow update, the dedicated stack passed **148 tests, 906 assertions**, covering the four request suites above plus asset-model/category behavior, catalogue field configuration, model default values, model API search, and category UI. Technical-search regression tests cover filtering beyond the first page, combined comparisons, text and checkbox matching, and rejection of hidden, unique, encrypted, or invalid numeric filters. Browser verification confirmed numeric and choice suggestions, combined filter chips, category persistence, and the standard table toolbar with no JavaScript errors. Browser verification as the seeded coordinator confirmed the Received Requests count of six, the linked candidate rows and project labels, the read-only controls, and the candidate-specific empty search message. Database readback confirmed 15 seeded assets, one coordinator target for the CAN-FD request, and no coordinator target for the assigned-only ECU Probe request.

Requester browser verification confirmed that the zero-inventory DC Supply and assigned-only ECU Probe are visible, the cart contains demand only, submission succeeds, and post-submission sourcing estimates remain visible. Database readback for sample request #3 confirmed quantity 4, the chosen project/site/discipline/date, shortfall 4, no physical assets, and no allocations.

## Simple catalogue walkthrough

The seeder maintains one model row for each catalogue case (three rows total). Earlier demo names are renamed in place on rerun. Inventory asset rows remain separate to cover the detailed sourcing cases.

| Model | Case | Expected inventory |
| --- | --- | --- |
| VN1630A | Mixed inventory | One reusable now, two due back, six assigned candidates, plus excluded statuses |
| LA-3080 | All assets assigned to another project | Two assets on BMW — Falcon Gateway and Ford — Vega Power Distribution; none reusable now |
| SM660-AR-11 | No inventory | No physical assets; request still allowed |

1. Pull the branch, run migrations, then rerun LearAssignedCandidatesQaSeeder.
2. Sign in as le-qa-requester (Lear-QA-2026!). Use Filters to select QA Automotive network interfaces. Expect VN1630A and LA-3080 in this category; QA Power supplies contains SM660-AR-11. Across both categories, expect the three model rows above and no Model Number column. Technical values are synthetic QA examples.
3. For a new request, set quantity, discipline VALIDATION, and destination IEC, then Add to Request. Open Request Cart and select a project/date. Existing active requests for the same model/project/site/discipline trigger duplicate protection; choose a different project if needed.
4. Submit SM660-AR-11 with quantity four. Confirm the submitted demand retains four units despite zero inventory. Submission does not allocate assets.
5. For sourcing, use the existing seeded requests for Renault — Atlas Body Control RFQ. Sign in as le-qa-coordinator and open Received Requests: VN1630A has six Assigned — release required candidates. Sign in as admin to inspect the assigned-only LA-3080 request, which has two assigned candidates on separate projects and no coordinator routing. Requester access to the candidate bucket is forbidden.

The demo has FMCS disabled. No availability counts are shown to requesters in the catalogue/cart; inspect sourcing only after submission.

## Resetting a disposable development database

Run these commands inside the intended development app container. The first command deletes all tables and data in that container's configured database, including current categories and accounts. The dedicated seeder recreates its own reference data, not your previous imported data.

```sh
php artisan migrate:fresh --force
php artisan db:seed --class=LearAssignedCandidatesQaSeeder --force
```

Do not run the generic DatabaseSeeder: it creates additional random demo models and assets. A fresh dedicated seed creates exactly three models, 19 assets (including one soft-deleted exclusion fixture), two categories, one manufacturer, one fieldset with two specifications, five disciplines, five companies, five project/RFQ records, one location, five statuses, four users, and three request lines in one submission. Migrations may also create their own system defaults. Disciplines: HARDWARE, SOFTWARE, PLATFORM, SYSTEM, VALIDATION. Companies: VLS, IEC, REC, SFLD, KRC.

LA-3080 covers multiple assigned assets across other projects: LE-QA-ONLY-ASSIGNED belongs to BMW — Falcon Gateway at IEC; LE-QA-ONLY-ASSIGNED-SECOND belongs to Ford — Vega Power Distribution at VLS. Both appear as Other project candidates for the seeded Renault — Atlas Body Control RFQ request. Actual RFQ-reserved assets are different: VN1630A includes LE-QA-RESERVED, which is excluded from available/assigned candidates and handled by RFQ reservation queries.

## Request sourcing names

Request views now use Requested Qty, Available Now, Due Back, In Use by Other Projects, Reserved for This RFQ, Reserved for Other RFQs, Estimated Qty to Procure, and Estimated Purchase Cost. Available/due-back figures still aggregate across sites; links remain permission-scoped. The This RFQ cell displays a dash for non-RFQ requests. Reservation buckets are identified by the configured RFQ-reserved asset status.

The Received Requests In Use by Other Projects column and its link use other_projects=1, excluding same-project and unlinked assets. VN1630A now shows four such candidates; LA-3080 shows two. Existing unfiltered assigned_candidates links remain compatible and include all project relationships. In-use candidates require release and do not reduce the estimated procurement quantity automatically.

## Simple sourcing export

Both Export Sourcing Breakdown (one model line) and Export Submission Sourcing (all active lines of one submission) download one worksheet and one table:

| Sourcing Group | Project / RFQ | Total Reference Price | Model | Quantity |
| --- | --- | ---: | --- | ---: |
| Available Now | BMW — Falcon Gateway | EUR 1,200.00 | VN1630A | 1 |
| Due Back | BMW — Falcon Gateway | EUR 1,200.00 | VN1630A | 1 |
| Due Back | Ford — Vega Power Distribution | EUR 1,200.00 | VN1630A | 1 |
| In Use | Project Missing | EUR 1,200.00 | VN1630A | 1 |

Sourcing groups are Available Now, Due Back, In Use, Reserved for RFQ, and Unavailable. Project/RFQ names identify the association without this/other distinctions in the report. Project Missing flags an assigned or reserved asset with no project link. Unassigned inventory with no project association displays No Project / RFQ.

Quantity is the physical asset count, not requested demand. Each asset ID contributes once even for repeated model lines. Rows are grouped by sourcing group, project/RFQ and model. Total Reference Price sums the current model prices of those unique assets, with the configured currency in the number format. Any missing price makes the group's total Unpriced; a real zero remains numeric zero. Deleted assets are excluded. Zero-inventory models add no rows. For a value-based chart, use the hierarchy columns and Total Reference Price, omitting Quantity from the chart input.

Due Back still means expected back on or before Needed By, excluding RFQ reservations; the short app heading and sourcing tab have tooltips explaining the deadline. The report has no tooltip, instructions, additional sheets or helper columns. In Use includes eligible assigned assets regardless of project link; RFQ reservations and assets due back by the deadline remain separate. App In Use counts and new drilldowns follow this broader scope, with legacy other_projects filters still supported.

Exports ignore active tab/search/pagination, respect asset visibility and request-review permissions, exclude canceled lines and another requester's matching batch ID, and require consistent shared project/date context. All lines must be reviewable by the exporter.

## Current demo submission and names

Rerun LearAssignedCandidatesQaSeeder after pulling. Existing project names and assets are updated in place; no reset is required. The three seeded model lines share batch cdd03130-26f8-4ab4-80f9-8da078114621, four requested units per model, one Needed By date and Renault RFQ context.

- Renault — Atlas Body Control RFQ
- Volkswagen — Orion Gateway RFQ
- Stellantis — Nova Power Distribution RFQ
- BMW — Falcon Gateway
- Ford — Vega Power Distribution

The old SAME fixture now belongs to the active BMW project instead of the requesting RFQ. Reserved fixture assets belong to actual RFQ records. NO-PROJECT remains an intentional data-quality example and appears as In Use / Project Missing.

As admin, open VN1630A from Received Requests, follow a sourcing link, and use Export Submission Sourcing. The table totals 18 physical assets and EUR 20,800: 16 VN1630A and two LA-3080. The single-model report totals 16 and EUR 19,200. VN1630A groups: Available Now 1, Due Back 2, In Use 6, Reserved for RFQ 4, Unavailable 3. The report stays at three seeded catalogue models and 19 seeded asset records including the deleted fixture.

## Simple two-line demo

Rerun `LearAssignedCandidatesQaSeeder` after pulling. It creates/updates two categories without deleting other data:
- QA Automotive network interfaces: VN1630A and LA-3080.
- QA Power supplies: SM660-AR-11.

As le-qa-requester, empty the cart and clear technical search chips. Add VN1630A quantity 4, then switch category to QA Power supplies and add SM660-AR-11 quantity 2. Use IEC, VALIDATION, and Renault — Atlas Body Control RFQ with its configured Needed By date. Submit both lines together, then open that new submission as admin / password.

Expected: two lines with quantities 4 and 2 and one shared submission context. VN1630A exposes the mixed inventory groups (Available Now 1, Due Back 2, In Use 6, Reserved for RFQ 4, Unavailable 3). SM660-AR-11 has no assets but retains its requested quantity 2. Submission does not reserve or allocate assets. The inventory export contains only VN1630A stock: 16 assets, EUR 19,200; the zero-stock model does not create an inventory row.
