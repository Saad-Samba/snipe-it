# Offboarding RAC Alerts Manual QA

Seed the deterministic dataset in the running application container:

```bash
php artisan db:seed --class=Database\\Seeders\\ManualOffboardingAlertsQaSeeder
```

The seeder is idempotent and creates:

- four disabled-account candidates that match by exact identifiers;
- one candidate with no LEAMS user and one with conflicting identifiers;
- one asset and one licence assigned in an exact company/discipline RAC scope;
- one accessory and one consumable assignment that must be ignored because the
  first implementation is limited to assets and licences;
- two non-person source rows that the script must skip.

Apply the audit-table migration:

```bash
php artisan migrate
```

Review the fixture without sending email:

```bash
php artisan snipeit:process-offboarding-report \
  database/seeders/fixtures/offboarding_alerts_disabled_accounts.csv
```

Exercise the complete mail path safely by redirecting every RAC notification to
one test mailbox:

```bash
php artisan snipeit:process-offboarding-report \
  database/seeders/fixtures/offboarding_alerts_disabled_accounts.csv \
  --send \
  --recipient-override=your.name@example.com
```

Expected report summary:

- candidate accounts: 6
- skipped non-person accounts: 2
- matched users: 4
- unresolved users: 2 (`not_found=1`, `ambiguous=1`)
- users with obligations: 1
- obligations: 2
- routing warnings: 0
- routed obligations: 2
- notification recipients: 1

The command is a dry run unless `--send` is present. It reads the application's
existing mail configuration. A repeated send of the same CSV and recipient mode
is skipped unless `--force` is supplied.

The seeded administrator is `qa-offboarding-admin` with password `password`.
