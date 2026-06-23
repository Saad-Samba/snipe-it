# License Expiration Investigation Environment

## Purpose

This environment is meant to help us answer a product question, not just a validation question:

`Should Snipe-IT make the license expiration date mandatory?`

The seeded data intentionally includes licenses with and without `expiration_date` so we can evaluate whether the missing date represents bad data, a valid perpetual-license workflow, or a case where `termination_date` is the better signal.

## How To Start It

From the repo root:

```sh
sh scripts/license-expiration-investigation-up.sh
```

This starts an isolated Docker Compose project using `dev.docker-compose.yml`, assigns dedicated host ports, and seeds a deterministic QA dataset.

To stop it later:

```sh
sh scripts/license-expiration-investigation-down.sh
```

To remove the named volumes too:

```sh
sh scripts/license-expiration-investigation-down.sh -v
```

## Login

- URL: printed by the startup script
- Username: `qa-license-admin`
- Password: `password`

## Seeded Records

- `QA Perpetual CAD Suite`
  - no expiration date
  - no termination date
  - represents a legitimate perpetual-license case
- `QA Annual Design Cloud`
  - future expiration date
  - represents a standard renewable subscription
- `QA Expired Security Scanner`
  - past expiration date
  - verifies expired-state UI and reporting behavior
- `QA Contractor Tooling Bundle`
  - no expiration date
  - future termination date
  - helps us evaluate whether termination date already covers some “mandatory expiration” asks
- `QA Vendor Managed Analytics`
  - both expiration and termination dates
  - lets us compare whether both fields are understood clearly in the UI

## Investigation Checklist

Use the environment to answer these questions before making `expiration_date` required:

1. Can admins create or edit legitimate license records that truly have no expiration date?
2. Does making expiration mandatory create friction for perpetual licenses or vendor-managed licenses where only a termination date is known?
3. Do expiring-license alerts, license reports, and license table filters become meaningfully better if every license must have an expiration date?
4. Would existing imports or historical records fail or need backfill if we enforce the field?
5. Is the real business rule “must have either expiration date or termination date” instead of “must have expiration date”?

## Recommendation Framing

If the no-expiration records feel artificial or unsupported in real workflows, that supports tightening validation.

If the no-expiration records feel realistic and useful, then making the field mandatory would likely be a product regression unless we replace it with a more flexible rule such as:

- require `expiration_date` for subscription-style licenses
- or require at least one of `expiration_date` / `termination_date`
