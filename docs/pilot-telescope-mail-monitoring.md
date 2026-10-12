# Pilot outbound-email monitoring

This temporary pilot feature records emails handled by Laravel Telescope while
leaving the configured SMTP mailer unchanged. It is intended for reviewing the
application's rendered outbound messages, not proving final delivery to a
recipient's mailbox.

## Enable on the pilot IIS host

Confirm the Telescope migrations have been applied, then add these values to
the deployed `.env` file:

```env
TELESCOPE_ENABLED=true
TELESCOPE_PILOT_MAIL_MONITORING=true
TELESCOPE_PILOT_MAIL_RETENTION_HOURS=48
```

Clear Laravel's cached configuration and recycle the IIS application pool. The
dashboard is available at `/telescope`; its Mail tab shows the captured
messages.

The existing Telescope authorization gate permits only Snipe-IT Super Users.
Restrict the route further at IIS or the network boundary if Super User access
is broader than the pilot administrators. Telescope entries can contain email
addresses and rendered message content, so retain them only for the agreed
pilot period.

The scheduled `telescope:prune` command retains entries for 48 hours by
default. Ensure the host's existing Laravel scheduler invokes `schedule:run`.

## End the pilot

Set both `TELESCOPE_ENABLED` and `TELESCOPE_PILOT_MAIL_MONITORING` to `false`,
clear cached configuration, and recycle the application pool. Continue using
the SMTP provider's logs or message trace for delivery, bounce, and complaint
evidence.
