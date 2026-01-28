# UI Search Guideline: Assets & Licenses

## Quick note on reports and API searches
If you need to search through reports or the API instead of the UI, follow your existing **Reports Search Guideline** or **API Search Guideline**. This document focuses only on the UI experience.

## Purpose
Use this guide to quickly and consistently find **assets** and **licenses** in the Snipe-IT UI. It covers the primary navigation paths, search fields, and filtering options so you can locate records by tag, name, serial, assignment, or other attributes.

## Before you start
- Ensure you can log in to the Snipe-IT web interface.
- Confirm you have permission to view Assets and Licenses.

## Asset search (UI)
### 1) Navigate to the Assets list
1. In the left navigation, select **Assets**.
2. Choose **All Assets** (or a scoped view like **Assigned**, **Checked Out**, or **Ready to Deploy** if you already know the status).

### 2) Use the global list search
1. Locate the **Search** field above the assets table.
2. Enter the most specific identifier you have first, such as:
   - **Asset Tag** (best for exact matches)
   - **Serial Number**
   - **Name/Hostname**
   - **Assigned To** (person or location)
   - **Model** or **Category**
3. Press **Enter** to run the search.

### 3) Refine with filters
1. Use the column filters or dropdown filters (if available) to narrow results, for example:
   - **Status** (e.g., Ready to Deploy, Deployed, Pending)
   - **Location**
   - **Company**
   - **Category**
2. Combine filters with the search field to reduce large result sets.

### 4) Open the asset record
1. Click the asset row to open the detail page.
2. Confirm the record by checking key fields such as **Asset Tag**, **Serial**, **Assigned To**, and **Status**.

### 5) If you cannot find the asset
- Try fewer search terms (e.g., use only the asset tag or only the serial number).
- Check if you are in a filtered view (Assigned, Ready to Deploy, etc.) and switch to **All Assets**.
- Verify you have access to the appropriate company or location scope.

## License search (UI)
### 1) Navigate to the Licenses list
1. In the left navigation, select **Licenses**.
2. Choose **All Licenses** (or a scoped view if available).

### 2) Use the license list search
1. Locate the **Search** field above the licenses table.
2. Enter a key identifier, such as:
   - **License Name**
   - **Product Key**
   - **Licensed User/Assigned To**
   - **Expiration Date** (if the UI supports date filtering)
3. Press **Enter** to run the search.

### 3) Refine with filters
1. Apply filters to limit results, such as:
   - **Expiration status** (e.g., Expired, Expiring Soon)
   - **Seats Available** or **Seats Used**
   - **Company**
2. Combine filters with the search field for more precise results.

### 4) Open the license record
1. Click the license row to open the detail page.
2. Confirm the record by checking **License Name**, **Product Key**, **Seats**, **Expiration**, and **Assigned To**.

### 5) If you cannot find the license
- Remove extra filters and try a broader search.
- Search by the license name first, then refine by seat or assignment details.
- Verify your permission scope for the license’s company or location.

## Tips and best practices
- Start with the most unique identifier (asset tag or product key).
- Avoid abbreviations unless they are part of the official record.
- If you frequently use the same filters, save a view (if your UI supports saved views).
- When searching for assigned hardware or licenses, verify the assignee name is spelled exactly as stored.

## Troubleshooting checklist
- Are you on **All Assets** or **All Licenses**?
- Are any filters still active?
- Do you have permission to view the record?
- Is the record archived or in a different company scope?

## Related guidance
- Reports Search Guideline (for report-based searches)
- API Search Guideline (for programmatic searches)
