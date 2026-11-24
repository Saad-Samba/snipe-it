# RAC reuse demo cases

Use `ReuseWorkflowDemoSeeder` only on an isolated, disposable LEAMS instance. It changes global settings and resets its named demo accounts and assets on rerun. It creates no checkout requests. Do not rerun it midway through recording; a rerun restores the asset states used by the exercises.

```bash
php artisan db:seed --class=ReuseWorkflowDemoSeeder --force
```

The seeder prints the shared demo password. Use a future **Needed By** date for each request. Sign in as `demo-EPL`, choose **Requests → Requestable Models**, add the model to the cart, and submit each case separately for **DEMO - BCM** and destination **Rabat**. Then switch to the named RAC account and open **Received Requests**. The RAC usernames and displayed names include their Site and discipline.

| Case | EPL request | RAC login | Asset and expected exercise |
| --- | --- | --- | --- |
| Local allocation | VN1630A CAN/LIN Interface × 1, HARDWARE | `demo-RAC-RABAT-HARDWARE` | Verify `DEMO-RF-VN-001` at Rabat, then allocate it through request-aware checkout. Confirm the assigned user, Project, request link, and Remaining quantity. |
| Cross-site transfer | J-Link PRO Debug Probe × 1, SOFTWARE | `demo-RAC-VALLS-SOFTWARE` | Verify `DEMO-RF-JLINK-001` at Valls and select **Start transfer**. After physical receipt, GSA changes the Site. GSA must also assign the prepared Rabat / SOFTWARE RAC scope before `demo-RAC-RABAT-SOFTWARE` can complete destination location, status, and checkout. |
| Unavailable after verification | E36313A DC Power Supply × 1, SYSTEMS | `demo-RAC-RABAT-SYSTEMS` | `DEMO-RF-PSU-001` initially appears as reusable. Inspect it and use the prepared *out for repair* outcome: edit its status to **Out for Repair** without assigning it to the request. Reopen the request, confirm the candidate is gone, then use **Mark no more reusable stock available**. `DEMO-RF-PSU-002` is already Out for Repair as a control. |

For the unavailable case, record what physical verification actually found. If the asset is held by a user rather than damaged, correct its real assignment through the normal checkout process instead of claiming a repair. Do not allocate an unavailable asset merely to close the request.

The transfer request also exposes the deliberate **Rabat / SOFTWARE** RAC assignment gap. `demo-RAC-RABAT-SOFTWARE` exists but is initially unassigned. GSA must assign that scope and run `php artisan snipeit:reconcile-rac-routing` before the receiving RAC segment. This can be shown as a routing-gap exercise, then closed before the transfer handoff.

The old `demo-RAC-VALLS` and `demo-RAC-RABAT` users are renamed to `demo-RAC-VALLS-SOFTWARE` and `demo-RAC-RABAT-HARDWARE` when this seeder is rerun. Existing submitted requests are not removed; start with a fresh disposable database for repeatable recordings.
