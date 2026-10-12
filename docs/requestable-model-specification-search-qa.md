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
| Landing page | Category cards appear. Choose **Communication**. |
| Category scope | The catalogue shows only Communication models and a **Technical filters** control group. |
| Controlled filter | Choose **Network Speed = 1 Gbps**. Only **Vector VN1630A CAN/LIN Interface** remains. |
| Numeric filter | Set **Port Count >= 8**. Only the Vector model remains. Change to **<= 2**. Only **PEAK PCAN-USB FD Interface** remains. |
| Existing search box | Search for `Copper`, `1 Gbps`, or a model name. The existing Bootstrap Table search narrows results without a second global search box. |
| Applied filters | A filter produces a removable chip and **Clear all** restores both models. |
| Sensitive controls | **QA Unique Serial Number** and **QA Encrypted Procurement Note** never appear as a technical filter or row specification. |
| Escape hatch | Return to the landing page and select **Search all models**. The existing text search works across the full requestable catalogue; category-only fields are not presented as misleading global filters. |
| Cart safety | A model still requires quantity, discipline, and destination site before **Add to Cart** succeeds. |

The seeded values live on models, not individual assets. In the administration
UI, changing a model's `Network Speed`, `Port Count`, or `Media Type` updates
the catalogue value immediately for every asset of that model.
