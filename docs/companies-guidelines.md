# Managing companies

## Overview
Companies can be used as a simple identifier field, or (when Full Multiple Companies Support is enabled) as a way to scope asset visibility and availability to users assigned to specific companies.【F:resources/lang/en-US/help.php†L26】

## Create a company
1. Open the Companies area and select **Create Company**.
2. Fill in the company profile fields such as name, phone, fax, email, notes, and tag color. These are the fields available on the company create/edit form.【F:resources/views/companies/edit.blade.php†L1-L41】
3. (Optional) Upload a company image/logo if desired.【F:resources/views/companies/edit.blade.php†L13-L14】
4. Save the company.

## Associate assets or licenses with a company
You can associate assets and licenses to a company by selecting a company on the item edit/create forms:

- **Assets:** The asset edit form includes a company selector, so you can assign the asset to a company while creating or editing it.【F:resources/views/hardware/edit.blade.php†L1-L22】
- **Licenses:** The license edit form includes the same company selector for assigning a license to a company.【F:resources/views/licenses/edit.blade.php†L1-L44】

> **Note:** When Full Multiple Companies Support is enabled and the current user is not a superadmin, the company selector is locked to the user’s scoped company in the UI.【F:resources/views/partials/forms/edit/company-select.blade.php†L1-L16】

## View licenses and assets assigned to a company
Open a company’s detail page to see everything assigned to it. The company view includes dedicated tabs for:

- **Assets** (with a company-filtered assets table).【F:resources/views/companies/view.blade.php†L16-L66】
- **Licenses** (company-filtered license list).【F:resources/views/companies/view.blade.php†L30-L36】【F:resources/views/companies/view.blade.php†L84-L105】
- **Accessories, consumables, components, and users** for a fuller picture of what’s associated with the company.【F:resources/views/companies/view.blade.php†L38-L63】【F:resources/views/companies/view.blade.php†L107-L159】【F:resources/views/companies/view.blade.php†L180-L219】

## Enable Full Multiple Companies Support (optional)
If you want company-based access control (e.g., users can only access their company’s assets), enable **Full Multiple Companies Support** in Admin Settings. This setting explicitly describes restricting users (including admins) to their company’s assets when enabled.【F:resources/lang/en-US/admin/settings/general.php†L165-L167】

## Extra tips
- Use company associations consistently for assets, licenses, accessories, consumables, components, and users, since those are all visible directly on the company detail page for auditing and reporting.【F:resources/views/companies/view.blade.php†L16-L219】
- If you’re adopting company scoping, set up your company structure first, then assign companies to users and inventory so the scoping rules apply cleanly.【F:resources/lang/en-US/admin/settings/general.php†L165-L167】
