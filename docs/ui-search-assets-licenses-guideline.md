# UI Search Guideline: General List Search (with Asset Exceptions)

## Quick note on reports and API searches
If you need to search through reports or the API instead of the UI, follow your existing **Reports Search Guideline** or **API Search Guideline**. This document focuses only on the UI experience.

## Purpose
Use this guide to search for data in the Snipe-IT UI. Most sections share a common table-list layout with a search field and filters. Assets are the main exception, because the Assets list includes **Advanced Search** and the Dashboard provides **Search by Asset Tag**.

## Common UI search pattern (most list pages)
### 1) Navigate to the list page
1. Use the left navigation to open the section you need (for example, **Licenses**, **Users**, **Accessories**, **Components**, etc.).
2. Choose the main list view such as **All [Items]** or the most appropriate scoped list.

### 2) Use the list search field
1. Locate the **Search** field above the table list.
2. Enter your most specific identifier first (for example, name, ID, serial number, or assigned user).
3. Press **Enter** to run the search.

### 3) Refine with filters
1. Use column filters or dropdown filters (if available) to narrow results.
2. Combine filters with the search field to reduce large result sets.

### 4) Open the record
1. Click the row to open the detail page.
2. Confirm the record by verifying key fields relevant to the item type.

### 5) If you cannot find the record
- Try fewer or more specific search terms.
- Remove filters and try again.
- Verify your permission scope for company, location, or status-based access.

## Asset-specific exceptions
Assets follow the common list pattern above, but they also include two extra options:

### 1) Advanced Search on the Assets list
1. Go to **Assets** → **All Assets**.
2. Select **Advanced Search** to search by multiple fields at once.
3. Add or adjust criteria such as **Asset Tag**, **Serial**, **Model**, **Status**, or **Location**.
4. Run the search and review the filtered results.

### 2) Search by Asset Tag on the Dashboard
1. Go to the **Dashboard**.
2. Use **Search by Asset Tag** for a quick lookup.
3. Enter the tag and submit to jump directly to the asset result.

## Tips and best practices
- Start with the most unique identifier (asset tag, serial, or record ID).
- Avoid abbreviations unless they are part of the official record.
- If you frequently use the same filters, save a view (if your UI supports saved views).
- When searching for assigned items, verify the assignee name is spelled exactly as stored.

## Troubleshooting checklist
- Are you on the correct list page (**All [Items]**)?
- Are any filters still active?
- Do you have permission to view the record?
- Is the record archived or scoped to a different company or location?

## Related guidance
- Reports Search Guideline (for report-based searches)
- API Search Guideline (for programmatic searches)
