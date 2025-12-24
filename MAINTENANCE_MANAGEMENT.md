# Maintenance Management Guide

This document explains how to manage asset maintenances in Snipe-IT today, including the upcoming maintenance alerts feature, and outlines future enhancements that could improve maintenance workflows.

## Overview

Snipe-IT tracks asset maintenances as first-class records attached to assets. Each maintenance can capture a type, schedule window, supplier, cost, notes, warranty status, and supporting files/URLs. Maintenances are visible in the UI, can be reported on, and can be exported for audits or external analysis.

## Creating and managing maintenances

### 1. Create a maintenance record

**UI path:** `Assets → Maintenances → Create`

When creating a maintenance, you can select one or more assets and then supply the following key fields:

* **Maintenance type** (maintenance, repair, upgrade, calibration, etc.)
* **Start date** and optional **completion date**
* **Supplier** (vendor or service provider)
* **Warranty flag**
* **Cost**
* **URL** (for a ticket, vendor portal, or external record)
* **Notes** (Markdown supported)
* **Image/attachment** (maintenance documentation)

Each maintenance is stored as its own record and linked back to the asset. You can create a single maintenance that applies to multiple assets by selecting multiple assets during creation.

### 2. Edit or complete a maintenance record

From the maintenances list, open a maintenance to update it. You can:

* Update the **completion date** when work finishes
* Add or adjust **notes**, **cost**, **supplier**, or **URL**
* Attach or replace supporting files

When a completion date is added, the application automatically calculates the maintenance duration.

### 3. View maintenance history on assets

Each asset’s maintenance history is accessible from the maintenance list and via asset details. This provides a complete chronological record of work performed against the asset.

### 4. Reporting and exports

**UI path:** `Reports → Asset Maintenance Report`

The maintenance report includes:

* Asset tag and asset name
* Supplier
* Maintenance type
* Title/Name
* Start and completion dates
* Calculated maintenance duration
* Cost

You can export this report for compliance, audits, or cost analysis.

## Maintenance alerts (upcoming maintenance notifications)

Snipe-IT now includes a daily alert command that warns administrators when a maintenance start date is approaching.

### How it works

* The command looks for maintenances with a **start_date between today and today + alert_interval**.
* Results are aggregated into a digest email.
* The command runs **daily** when alerts are enabled.

### Configuration

These settings are required for alert delivery:

1. **Alerts enabled** in Settings → Alerts.
2. **Alert email** set (one or multiple addresses).
3. **Alert interval** (in days) configured. This is the same “Expiring Alerts Threshold” used by other alerts.

### Running the alert command manually

You can trigger the alert manually to validate configuration:

```
php artisan snipeit:upcoming-maintenances --with-output
```

The `--with-output` flag prints a table of upcoming maintenances in the console, which is useful for debugging or auditing.

### Viewing alert results

Alert emails are sent to the configured admin email address(es). The email includes a limited list of upcoming maintenances and a link to the maintenance list view.

## Operational tips

* **Normalize maintenance types** so you can filter and report consistently.
* **Always capture completion dates** so you get accurate durations.
* **Attach supporting files** (invoices, work orders, calibration results).
* **Use suppliers** consistently for vendor performance tracking.

---

## Next steps (future improvements)

Below are enhancements that would make maintenance management even more robust:

1. **Recurring maintenance schedules**
   * Define maintenance intervals (e.g., every 6 months) and auto-generate upcoming maintenance records.

2. **Assigned technician or team**
   * Add a responsibility field so you can track who owns the work, not just what happened.

3. **Downtime tracking**
   * Record asset downtime or service impact to calculate MTTR and business impact.

4. **Maintenance status workflow**
   * Introduce states like “Scheduled → In Progress → Completed” with timestamps.

5. **Calendar view**
   * Display upcoming and historical maintenances on a calendar for planning.

6. **Notification targeting**
   * Allow maintenance alerts to go to a maintenance team distribution list or supplier contacts.

7. **Cost roll-ups**
   * Aggregate maintenance costs per asset or supplier for lifecycle cost analysis.

These improvements would complement the existing feature set and make Snipe-IT even stronger for preventative maintenance management.
