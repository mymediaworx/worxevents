# Known limitations

Phase 2 adds events, venues, free registration, and digital tickets. These are still absent on purpose.

- No card charge, no Stripe HTTP client, and no Authorize.net nonce. Both refund adapters refuse to send. Paid ticket types can be saved, and checkout refuses them until a processor is connected.
- Check-in and box office routes are still shells. Door scanning and walk-up sales are later.
- Ticket delivery is an SVG QR code and a one-page PDF drawn from the QR matrix. PNG QR codes are not generated, because the app does not require GD.
- The raw ticket token is only in the email attachments. The database stores its SHA-256. A failed send can be replaced from the order; the old token is voided.
- No webhook endpoint and no route that marks a refund complete.
- The fee ledger posts `assessed` only. `collected`, `owed`, and `remitted` can be written by `postLedger` and are not wired to money movement.
- Fee rules created in the platform UI are platform-wide (`organization_id` null). The resolver can prefer an organization rule, and there is no screen to create one.
- USD only. One fee label. Michael approved the fee and refund terms on 2026-10-26 ([DECISIONS.md](DECISIONS.md)). Tax treatment, wording, and merchant of record are still open external reviews.
- Cash and comped box-office orders do not assess a fee.
- Users are global. Isolation is the hostname plus membership, not a database per organization.
- Organization sync trusts the hostname in config and does not query DNS.
- The first platform administrator is a console command, not a signup.
- Invitations cannot transfer ownership.
- Mail is synchronous. The database queue does nothing until cron runs `schedule:run`.
- Sessions and cache use the database. There is no Redis.
- Pagination stops at 100 rows.
- A formal WCAG audit has not been run. Tokens and targets are aimed at WCAG 2.2 AA.
- SiteGround for this domain has not been measured ([HOSTING.md](HOSTING.md)).
- CI is written for MySQL 8.4 and MariaDB 11.8. The machine that produced this phase ran PHPUnit on MySQL 8.0.46 only. Docker was not available here, so those two engines were not executed in this run.
- Deploy reads SiteGround and database credentials from GitHub Actions secrets. Those values are not in the repo. The workflow does not call Stripe.
- Node and Vite are not part of the app. An upload that contains `node_modules` has that directory removed by the release script.
