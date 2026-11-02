# Required asset Discipline QA

The change requires an existing active Discipline for new assets created through the web form, API, and CSV importer. CSV imports map a discipline **name**; API callers supply `discipline_id`. Unknown and soft-deleted disciplines are rejected. The create/clone form shows a required marker, HTML `required`, and `aria-required`. The importer exposes `Discipline (required for new assets)` and recognizes a `Discipline` CSV header.

Existing records may retain a blank discipline during normal edits and import updates. An omitted or blank update cell preserves the saved discipline. An explicit nonblank unknown discipline fails that import row. No migration, backfill, database constraint, or database default is added; `assets.discipline_id` remains nullable. Imports retain the existing partial-success behavior: valid rows are saved even when another row fails. Errors identify rejected asset tags.

## Running environment

- Branch: `feature/required-asset-discipline`, based on `origin/feature/reuse-workflow`.
- Worktree: `/Users/saadsamba/.codex/worktrees/required-asset-discipline/snipe-it`.
- Compose project: `required-asset-discipline`; config: `docker-compose.discipline-qa.yml`.
- App: <http://localhost:8142>; MailHog: <http://localhost:8242>.
- Login: `discipline.qa` / `Discipline-QA-2026!` (synthetic local QA account).
- All databases, volumes, and network are dedicated to this stack. The database has no published host port.

From this worktree, to provision a fresh environment:

```sh
cp .env.example .env
cp .env.testing.example .env.testing
docker compose -f docker-compose.discipline-qa.yml up -d --build
docker compose -f docker-compose.discipline-qa.yml exec -T --user apache app php artisan migrate --force
docker compose -f docker-compose.discipline-qa.yml exec -T --user apache app php artisan db:seed --class=AssetDisciplineQaSeeder --force
```

The seeder is repeatable and does not reset existing asset values. It creates the QA site, family, manufacturer, model, status, active discipline, user, two baseline assets, and two preloaded import files. The current stack reuses a compatible local PHP runtime image under the feature-specific image tag, with this worktree mounted as the application source.

## Manual scenarios

Use `Discipline QA Site`, `Discipline QA Model`, `Discipline QA Ready`, and `Discipline QA Hardware`. FMCS is off in the isolated database; automated suites also cover FMCS on/off and scoped users.

| Scenario | Expected |
| --- | --- |
| Create an asset with Discipline blank | Visible required marker and validation error; nothing saved |
| Create one or multiple assets with the QA discipline | Every new asset retains the selected discipline |
| Clone `QA-DISC-LEGACY` | Discipline required because the clone creates a new record |
| Edit `QA-DISC-LEGACY` without filling Discipline | Edit succeeds and Discipline stays blank |
| POST `/api/v1/hardware` without Discipline, with null/empty/invalid/deleted ID | Error keyed by `discipline_id`; no asset created |
| POST with a valid discipline ID | Success and stored ID matches |
| PATCH a legacy blank without `discipline_id` | Other fields update and Discipline stays blank |
| Import preloaded `qa-discipline-new-assets.csv` | Map its seven columns to Asset Tag, Model Name, Category, Manufacturer, Site, Status, and Discipline. The valid row saves; blank and unknown rows show errors with their tags |
| Import preloaded `qa-discipline-legacy-update.csv` | Enable **Update Existing Values** and map Asset Tag, Asset Name, and Discipline. `QA-DISC-LEGACY` stays blank; `QA-DISC-SET` retains its discipline; both names update |

The two source samples are `new-assets.csv` and `legacy-update.csv` in this directory. Preloaded samples initially show unmapped columns; choose the mappings above. After the new-asset sample has succeeded once, enable **Update Existing Values** on repeat runs to avoid an expected duplicate-tag error.

The asset detail template also includes a small prerequisite fix: an inline `@php(...)` directive was changed to a complete `@php` block because the base template otherwise compiled to a PHP syntax error and returned HTTP 500.

## Automated validation

A separate database keeps the live QA data intact:

```sh
docker compose -f docker-compose.discipline-qa.yml exec -T db mariadb -uroot -pdiscipline-qa-root-local -e "CREATE DATABASE IF NOT EXISTS discipline_testing; GRANT ALL PRIVILEGES ON discipline_testing.* TO 'discipline_qa'@'%';"
docker compose -f docker-compose.discipline-qa.yml exec -T --user apache -e DB_CONNECTION=mysql -e DB_DATABASE=discipline_testing app php artisan test tests/Feature/Assets/DisciplineValidationTest.php tests/Feature/Assets/Api/StoreAssetTest.php tests/Feature/Assets/Api/StoreAssetWithFullMultipleCompanySupportTest.php tests/Feature/Assets/Api/UpdateAssetTest.php tests/Feature/Assets/Ui/StoreAssetsTest.php tests/Feature/Assets/Ui/StoreAssetWithFullMultipleCompanySupportTest.php tests/Feature/Assets/Ui/SerialRequirementFormTest.php tests/Feature/Assets/Ui/ShowAssetTest.php tests/Feature/Importing/Api/ImportAssetDisciplineTest.php tests/Feature/Importing/Api/ImportAssetsTest.php tests/Feature/Importing/Ui/ImportTest.php --compact
```

Verified on 2026-11-02: **130 passed, 575 assertions; 12 incomplete**. The incomplete cases explicitly skip existing custom-field scenarios on MySQL. All new Discipline cases pass. Browser checks verified blank-field blocking, successful creation and detail display, mixed valid/invalid import errors, and successful legacy-update imports. Database readback verified the legacy NULL and existing discipline values, only valid new rows persisted, and the column is still nullable. PHP lint and `git diff --check` passed.

Notion validation rule: [VR-5: discipline](https://www.notion.so/318c58d3ec8e81c4b730c8f9ff81a37a). Enforcement is UI/API/Import; deployment awaits PR review and merge.
