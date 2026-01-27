# Viewing licenses assigned to a project or company

Use the following options in the Snipe-IT UI to see which licenses are assigned to a specific project or company.

## 1) Project detail page (licenses tab)

**Goal:** See all licenses assigned to a single project.
**Navigation:** Projects → {Project Name} → Licenses tab.
**PUR placeholder:** [PUR-PLACEHOLDER: project licenses tab GIF]

1. Open the project you want to review.
2. Select the **Licenses** tab to see all licenses assigned to that project.
3. Use the table controls (search, sort, export) to inspect or export the list.

This tab is backed by the project license table, which loads license data filtered by the project’s ID.

## 2) Company detail page (licenses tab)

**Goal:** See all licenses assigned to a single company.
**Navigation:** Companies → {Company Name} → Licenses tab.
**PUR placeholder:** [PUR-PLACEHOLDER: company licenses tab GIF]

1. Open the company you want to review.
2. Select the **Licenses** tab to see all licenses assigned to that company.
3. Use the table controls (search, sort, export) to inspect or export the list.

This tab is backed by the company license table, which loads license data filtered by the company’s ID.

## 3) License detail page (company/project attribution)

**Goal:** Jump from a license to its related company or project.
**Navigation:** Licenses → {License Name} → Details panel.
**PUR placeholder:** [PUR-PLACEHOLDER: license detail links GIF]

If you are already viewing a specific license, the **Details** section displays the associated company and project (when present). You can follow those links to jump directly to the company or project detail pages for a full license list.

## 4) Third-party integrations (API or BI tools)

**Goal:** Report on license assignments outside the UI.
**Navigation:** External tool → Snipe-IT API connection → License query.
**PUR placeholder:** [PUR-PLACEHOLDER: third-party reporting GIF]

Use third-party tools that can connect to the Snipe-IT API (such as BI platforms, reporting tools, or internal dashboards) to query license assignments by project or company. The license data can be filtered by the project or company identifiers that your integration supports.

## 5) Exported reports (CSV)

**Goal:** Export license assignments to CSV.
**Navigation:** Projects/Companies → {Name} → Licenses tab → Export, or Reports → Licenses.
**PUR placeholder:** [PUR-PLACEHOLDER: export/report GIF]

From the project or company **Licenses** tab, use the export control to download a CSV of assigned licenses. You can also use the global **Reports → Licenses** export and filter the file by company or project as needed in a spreadsheet tool.
