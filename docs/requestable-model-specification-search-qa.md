# Requestable model specification search: manual QA

Use a disposable Snipe-IT environment. The seeder creates deterministic demo
data and can safely be run again:

```sh
php artisan db:seed --class=Database\\Seeders\\RequestableModelSpecificationSearchSeeder
```

Log in as `demo-EPL` with password `password`, then open
`/account/requestable-assets`.

| Scenario | Expected result |
| --- | --- |
| Category selector | Choose **Communication** to limit the existing table to that category. |
| Visible specifications | The two Communication models show Network Speed, Port Count, and Media Type in the **Technical specifications** column. |
| Existing search box | Search for `Copper`, `1 Gbps`, or a model name. The existing Bootstrap Table search narrows results without a second global search box. |
| Sensitive controls | **QA Unique Serial Number** and **QA Encrypted Procurement Note** never appear as a row specification or in search data. |
| Other categories | Select another category. Its own configured fields appear on its models; unrelated fields do not become global columns. |
| Cart safety | A model still requires quantity, discipline, and destination site before **Add to Cart** succeeds. |

The seeded values live on models, not individual assets. In the administration
UI, changing a model's `Network Speed`, `Port Count`, or `Media Type` updates
the catalogue value immediately for every asset of that model.
