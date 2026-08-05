# Offboarding RAC Alerts Manual QA

Seed the deterministic dataset in the running application container:

```bash
php artisan db:seed --class=Database\\Seeders\\ManualOffboardingAlertsQaSeeder
```

The seeder is idempotent and creates:

- four disabled-account candidates that match by exact identifiers;
- one candidate with no LEAMS user and one with conflicting identifiers;
- one asset and one licence assigned in an exact company/discipline RAC scope;
- one accessory routed through the only RAC in its company;
- one consumable in a company with multiple RACs and no discipline, producing a warning;
- two non-person source rows that the script must skip.

Run the external script with:

```bash
python3 utilities/offboarding_rac_alerts.py \
  --csv /path/to/snipe-it/database/seeders/fixtures/offboarding_alerts_disabled_accounts.csv \
  --env development
```

Expected report summary:

- candidate accounts: 6
- skipped non-person accounts: 2
- matched users: 4
- unresolved users: 2 (`not_found=1`, `ambiguous=1`)
- users with obligations: 3
- obligations: 4
- routing warnings: 1
- routed obligations: 3

The seeded administrator is `qa-offboarding-admin` with password `password`.
