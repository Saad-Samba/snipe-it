# Dashboard filtering with the **Discipline** custom field

Use these steps to ensure the dashboard filters for **company** and **Discipline** work correctly.

## 1) Create the custom field
1. Go to **Admin → Custom Fields → Add New**.
2. Name the field exactly **“Discipline”** (capitalization matters).
3. Choose an input type that matches your data (List box for controlled values is recommended; Text works if you need free-form).
4. Save the field and add it to the asset fieldset(s) that should capture discipline data.

> Why: The dashboard expects the custom field to exist so it can use the `_snipeit_discipline` column on `assets`.

## 2) Populate data on assets/licenses
1. For each asset, set the **Discipline** value in the asset form (or via import/API).
2. Optionally, align any license-associated assets with the same Discipline so license counts respect the filter.

> Tip: If you manage disciplines centrally, prefer a list box to avoid typos.

## 3) Use the dashboard filters
* Open the dashboard and select **Company** and/or **Discipline** in the filter bar, then click **Filter**.
* Counts, recent activity, and the status pie chart will refresh using those filters.
* The filter state is passed to the chart APIs, so the pie chart matches the filtered dataset.

### About licenses
* The dashboard’s license count currently filters by the Discipline value on related assets. Licenses themselves don’t yet have a Discipline field.
* To track Discipline directly on licenses, add a **Discipline** custom field to licenses too. After that, update reporting/filters to combine both the license’s own Discipline and the Discipline of any asset seats (if relevant for your process).

## 4) Avoid deleting the Discipline field
* Deleting the custom field drops the `assets` column and all Discipline values, which breaks the filter.
* If you need to hide it temporarily, remove it from forms/fieldsets instead of deleting it.

## 5) Troubleshooting
* If the Discipline dropdown is disabled, the custom field is missing—recreate it with the exact name.
* No options showing? Ensure at least one asset has a Discipline value; the dropdown lists distinct values from assets (and respects the selected company).
