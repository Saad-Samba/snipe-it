# Assigned request candidates: implementation and local QA

## Behavior

The existing Received Requests inbox links to **Assigned — release required**. Candidate assets must match the exact catalog model, be checked out, have an operational (deployable, non-pending, non-archived) status, and not be deleted or RFQ-reserved. All checkout targets are supported. Project relationship comes from `assets.project_id`: Same project, Other project, or No project.

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

All four local QA accounts use password `Lear-QA-2026!`:

| Username | Purpose |
| --- | --- |
| `le-qa-coordinator` | Received Requests and candidate review |
| `le-qa-admin` | Superuser review, including the assigned-only request |
| `le-qa-requester` | Owns requests; new candidate bucket must return 403 |
| `le-qa-outsider` | Has asset-list permission; new candidate bucket must return 403 |

FMCS is off in the demo so the coordinator can see the synthetic IEC and VLS assets. Do not interpret that as cross-company authorization when FMCS is enabled. Automated tests cover company and category visibility separately.

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
| LA-3080 | All assets assigned to another project | Two assets on QA Legacy Gateway Validation and QA Power Distribution Validation; none reusable now |
| SM660-AR-11 | No inventory | No physical assets; request still allowed |

1. Pull the branch, run migrations, then rerun LearAssignedCandidatesQaSeeder.
2. Sign in as le-qa-requester (Lear-QA-2026!). Use Filters to select QA Automotive network interfaces. Expect exactly the three model rows above and no Model Number column. Technical values are synthetic QA examples.
3. For a new request, set quantity, discipline VALIDATION, and destination IEC, then Add to Request. Open Request Cart and select a project/date. Existing active requests for the same model/project/site/discipline trigger duplicate protection; choose a different project if needed.
4. Submit SM660-AR-11 with quantity four. Confirm the submitted demand retains four units despite zero inventory. Submission does not allocate assets.
5. For sourcing, use the existing seeded requests for QA Next-generation Body Controller. Sign in as le-qa-coordinator and open Received Requests: VN1630A has six Assigned — release required candidates. Sign in as le-qa-admin to inspect the assigned-only LA-3080 request, which has two assigned candidates on separate projects and no coordinator routing. Requester access to the candidate bucket is forbidden.

The demo has FMCS disabled. No availability counts are shown to requesters in the catalogue/cart; inspect sourcing only after submission.

## Resetting a disposable development database

Run these commands inside the intended development app container. The first command deletes all tables and data in that container's configured database, including current categories and accounts. The dedicated seeder recreates its own reference data, not your previous imported data.

```sh
php artisan migrate:fresh --force
php artisan db:seed --class=LearAssignedCandidatesQaSeeder --force
```

Do not run the generic DatabaseSeeder: it creates additional random demo models and assets. A fresh dedicated seed creates exactly three models, 19 assets (including one soft-deleted exclusion fixture), one category, one manufacturer, one fieldset with two specifications, five disciplines, five companies, five project/RFQ records, one location, five statuses, four users, and two requests. Migrations may also create their own system defaults. Disciplines: HARDWARE, SOFTWARE, PLATFORM, SYSTEM, VALIDATION. Companies: VLS, IEC, REC, SFLD, KRC.

LA-3080 covers multiple assigned assets across other projects: LE-QA-ONLY-ASSIGNED belongs to QA Legacy Gateway Validation at IEC; LE-QA-ONLY-ASSIGNED-SECOND belongs to QA Power Distribution Validation at VLS. Both appear as Other project candidates for the seeded QA Next-generation Body Controller request. Actual RFQ-reserved assets are different: VN1630A includes LE-QA-RESERVED, which is excluded from available/assigned candidates and handled by RFQ reservation queries.

## Request sourcing names

Request views now use Requested Qty, Available Now, Due Back by Needed Date, In Use by Other Projects, Reserved for This RFQ, Reserved for Other RFQs, Estimated Qty to Procure, and Estimated Purchase Cost. Available/due-back figures still aggregate across sites; links remain permission-scoped. The This RFQ cell displays a dash for non-RFQ requests. Reservation buckets are identified by the configured RFQ-reserved asset status.

The Received Requests In Use by Other Projects column and its link use other_projects=1, excluding same-project and unlinked assets. VN1630A now shows four such candidates; LA-3080 shows two. Existing unfiltered assigned_candidates links remain compatible and include all project relationships. In-use candidates require release and do not reduce the estimated procurement quantity automatically.

## Excel sourcing export

Open a request's Assets view as its authorized coordinator or superuser and click **Export Sourcing Breakdown** beside the sourcing navigation. Every tab exports the same full model inventory visible to that user, ignoring tab/search/pagination filters. Deleted assets are excluded. The workbook opens on Sunburst Data, a grouped chart-ready table named SunburstData, and also contains Request Summary and the detailed Asset Breakdown table named SourcingAssets. The summary separates all-sites procurement estimates from permission-scoped asset counts.

Each asset has one group, with priority reservation > available > due back by needed date > eligible in use > unavailable. Reservations distinguish actual RFQs from active projects. No needed date means no due-back deadline. Asset Count is numeric 1 per row. Return and export dates are typed Excel dates; imported labels are literal text rather than executable formulas.

In Excel: open Sunburst Data and select its three columns (Sourcing Group, Project / RFQ, Asset Count), including the headers and populated rows. Choose Insert > Hierarchy Chart > Sunburst. No PivotTable, copy/paste, or subtotal removal is needed. For a single-ring pie, aggregate Asset Count by Sourcing Group in a PivotTable. Requested quantity and procurement estimates remain in Request Summary, outside the inventory chart. Zero-inventory exports keep both data tables empty with headers and retain the request summary.

## Complete Sunburst demo: VN1630A

Rerun LearAssignedCandidatesQaSeeder after pulling. It keeps three model rows and now seeds 19 total asset rows (one soft deleted), including 16 visible VN1630A assets. The target request is QA Next-generation Body Controller, now explicitly flagged as an RFQ with Needed By 14 days after seeding. There are two additional RFQs: QA Gateway RFQ and QA Power Distribution RFQ.

As le-qa-admin, open VN1630A's seeded request, follow any sourcing link to Assets, then Export Sourcing Breakdown. With full demo visibility, Sunburst Data totals should be:

| Sourcing group | Assets | Breakdown |
| --- | ---: | --- |
| Available Now | 1 | Legacy Gateway project association retained; asset is unassigned |
| Due Back by Needed Date | 2 | One each on Legacy Gateway Validation and Power Distribution Validation, returning in 7 and 10 days |
| In Use by This Project | 1 | Next-generation Body Controller |
| In Use by Other Projects | 4 | Legacy Gateway Validation |
| In Use Without a Project | 1 | Unassigned project |
| Reserved for This RFQ | 1 | Next-generation Body Controller |
| Reserved for Other RFQs | 2 | Gateway RFQ and Power Distribution RFQ |
| Reserved for Other Projects | 1 | Legacy Gateway Validation, an active project |
| Unavailable | 3 | Repair, pending, and archived assets |
| **Total** | **16** | Each physical asset counted once; deleted fixture omitted |

The detailed asset sheet retains expected return dates. Reserved assets stay in their reservation slice even if they also have a checkout or return date, so a Sunburst never double-counts them. Procurement estimates continue to follow the existing request calculation; an asset held for another RFQ or in use is not automatically available to this request. Rerunning the seeder resets the demo dates relative to today.
