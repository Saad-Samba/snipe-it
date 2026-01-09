# Asset retirement workflow

This playbook outlines how to retire an asset in Snipe-IT, the roles involved, and the inputs/outputs passed between steps.

## Roles

- **Requester / Business owner** – initiates retirement, provides business reason and timing.
- **IT technician** – collects the asset, performs check-in and data sanitization.
- **Asset manager** – updates status labels, archives the asset record, and maintains documentation.
- **Finance / Compliance reviewer** (optional) – reviews disposal evidence and closes out records for audits.

## Workflow

### 1) Initiate retirement
- **Role:** Requester / Business owner
- **Action in app:** Locate the asset record and add a note with the retirement reason and requested date.
- **Inputs:** Business justification, target retirement date, asset link.
- **Outputs:** Documented intent on the asset timeline that downstream roles can reference.

### 2) Retrieve and check in the asset
- **Role:** IT technician
- **Action in app:** Use **Checkin** on the asset detail page to remove the assignment, record the collection location, and add pickup notes (e.g., accessories returned).
- **Inputs:** Physical asset, accessories, collection details.
- **Outputs:** Asset is unassigned; check-in note recorded in history for auditability.

### 3) Sanitize and inspect
- **Role:** IT technician
- **Action in app:** Add notes to the asset with the sanitization method (e.g., wipe type) and inspection results; upload supporting certificates or photos in the **Files** tab.
- **Inputs:** Sanitization checklist, disposal certificates, photos.
- **Outputs:** Traceable evidence attached to the asset record; clear indication it is safe to retire.

### 4) Move to an archived/retired status
- **Role:** Asset manager
- **Action in app:**
  1) Ensure a non-deployable **Status Label** exists for retired/archived items.
  2) Edit the asset and change **Status** to that archived label (these assets cannot be checked out and are intended only for historical reference).
- **Inputs:** Sanitization confirmation, archived status label.
- **Outputs:** Asset is excluded from normal deployable pools; it appears in the **Archived** view and respects visibility settings.

### 5) Update location and availability controls
- **Role:** Asset manager
- **Action in app:** Set the asset’s **Location** to the storage or disposal facility and clear any **Requestable** flag so it cannot be requested.
- **Inputs:** Storage/disposal location.
- **Outputs:** Accurate inventory counts by location; asset cannot be mistakenly requested.

### 6) Financial/compliance closeout (optional)
- **Role:** Finance / Compliance reviewer
- **Action in app:** Download or export the asset record and attached evidence; add a closing note referencing any external approval or write-off ID.
- **Inputs:** Disposal approval IDs, write-off references.
- **Outputs:** Audit-ready record linking finance approval to the retired asset.

### 7) Visibility and reporting
- **Roles:** Asset manager, auditors
- **Action in app:** Use the **Archived** filter/view for operational reviews. If necessary, toggle the setting to include archived assets in “all assets” listings for broader audits, then revert to keep day-to-day views clean.
- **Inputs:** Audit scope, reporting needs.
- **Outputs:** Consistent reporting that either isolates or includes retired assets as required.

## Summary of handoffs
- Retirement request ➜ technician (notes on asset).
- Sanitization evidence ➜ asset manager (files/notes).
- Archived status change ➜ finance/compliance (for optional closeout).
- Final records ➜ auditors (exports/reports from the Archived view).
