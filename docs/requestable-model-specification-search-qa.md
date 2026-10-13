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
| Multiple fieldsets | Filter to **Embedded Development Tools** and use Communication Protocol is Ethernet. Only the TRACE32 model remains. Then filter to **Communication** and use Port Count >= 8; only the Vector model remains. |
| Field suggestion | In the same search box, type **Network** and choose **Network Speed** from the suggestions. |
| Controlled value chip | After selecting Network Speed, choose **1 Gbps**. It becomes a removable **Network Speed is 1 Gbps** chip and leaves only the Vector model. |
| Numeric chip | Type **Port Count**, choose an operator such as **>=**, enter **8**, then press Enter. Only the Vector model remains. Use **<= 2** for the PEAK model. |
| Multiple chips | Add Network Speed is 1 Gbps and Port Count >= 8. Results must satisfy both conditions. Remove a chip with its × button. |
| Existing search box | Search for `Copper`, `1 Gbps`, or a model name. The existing Bootstrap Table search narrows results without a second global search box. |
| Sensitive controls | **QA Unique Serial Number** and **QA Encrypted Procurement Note** never appear as a row specification or in search data. |
| Other categories | Select another category. Its own configured fields appear on its models; unrelated fields do not become global columns. |
| Cart safety | A model still requires quantity, discipline, and destination site before **Add to Cart** succeeds. |

The seeded values live on models, not individual assets. In the administration
UI, changing a model's `Network Speed`, `Port Count`, or `Media Type` updates
the catalogue value immediately for every asset of that model.
