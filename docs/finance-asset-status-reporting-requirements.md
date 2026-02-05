# LEAMS Asset Status & Location Change Reporting Requirements

## Background
Finance requires timely notification of asset status and location changes recorded in LEAMS so they can prepare documentation, approvals, and assess financial statement impacts. Finance currently does not have direct access to LEAMS.

## Goals
- Ensure Finance is informed of all asset status changes that affect financial reporting.
- Ensure Finance is informed of all asset location changes that can affect cost allocation and statutory reporting.
- Provide an automated, periodic report to reduce manual coordination.

## Stakeholders
- **Primary:** Finance team
- **Secondary:** Asset management/operations, IT/LEAMS administrators

## User Stories
1. **As a Finance user**, I want to receive a periodic report of asset status changes so that I can prepare documentation and approvals on time.
2. **As a Finance user**, I want to receive a periodic report of asset location changes so that I can validate cost center and entity assignments.
3. **As a LEAMS admin**, I want an automated process to generate and distribute the report so that changes are consistently communicated without manual effort.

## Scope
### In Scope
- Status changes for the following statuses:
  - Scrap
  - Obsolescence
  - Not in use (triggers impairment assessment)
- Location changes, including:
  - Change of Cost Center
  - Change of entity location (e.g., transfer from Valls Tech Center to Rabat Tech Center)
  - Indication of whether the move is **temporary** or **permanent**
- Automated, periodic report generation and distribution to Finance (email and/or shared drive)

### Out of Scope (for this phase)
- Granting Finance direct access to LEAMS
- Real-time notifications or alerts (only periodic report)
- Historical data backfill (unless explicitly requested later)

## Functional Requirements
### Reporting Frequency
- The report is generated on a **periodic schedule** (cadence to be confirmed, e.g., daily/weekly/monthly).

### Report Content (Minimum Fields)
- Asset identifier (e.g., asset tag/ID)
- Asset description/name
- Status change details:
  - Previous status
  - New status
  - Date/time of change
- Location change details (if applicable):
  - Previous cost center
  - New cost center
  - Previous entity location
  - New entity location
  - Temporary/permanent indicator
  - Date/time of change
- Change actor (user/system) if available

### Distribution
- Report is delivered to Finance via:
  - Email distribution list, and/or
  - Shared drive location

### Access & Visibility
- Finance can access the report without logging into LEAMS.

## Acceptance Criteria
1. **Status Change Reporting**
   - Given an asset status changes to Scrap, Obsolescence, or Not in use,
   - When the reporting job runs,
   - Then the report includes the asset ID, status change details, and timestamp.

2. **Location Change Reporting**
   - Given an asset’s cost center or entity location changes,
   - When the reporting job runs,
   - Then the report includes previous and new values and whether the change is temporary or permanent.

3. **Distribution**
   - Given the reporting job completes successfully,
   - When Finance checks the configured delivery channel(s),
   - Then the report is available without requiring LEAMS access.

4. **Auditability**
   - Given an asset change was recorded in LEAMS during the reporting period,
   - When Finance reviews the report,
   - Then the change appears exactly once with correct details.

## Assumptions
- LEAMS records status and location changes with timestamps.
- LEAMS can expose required data for reporting (e.g., via export/API/db access).

## Dependencies
- Confirmation of reporting cadence (daily/weekly/monthly).
- Identification of delivery channel(s) and recipients.
- Clarification on required data fields beyond the minimum set.

## Risks & Mitigations
- **Risk:** Missing change indicators (e.g., temporary vs permanent).
  - **Mitigation:** Add or validate a field in LEAMS to capture move type.
- **Risk:** Data quality issues in LEAMS.
  - **Mitigation:** Add validation or audit checks in the reporting process.

## Open Questions
1. What reporting cadence does Finance prefer (daily/weekly/monthly)?
2. What delivery method is preferred (email, shared drive, or both)?
3. Are there additional statuses or change types Finance wants to track?
4. Should the report include changes made by system integrations as well as users?
5. Is a backfill of historical changes required for an initial report?
