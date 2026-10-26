# Known limitations

Phase 1 is the foundation. These are absent on purpose.

- No events, ticket types, holds, orders, QR codes, check-in scans, or box-office sales. Those routes are shells behind permissions.
- No card charge, no Stripe HTTP client, and no Authorize.net nonce. Both refund adapters refuse to send.
- No webhook endpoint and no route that marks a refund complete.
- The fee ledger posts `assessed` only. `collected`, `owed`, and `remitted` can be written by `postLedger` and are not wired to money movement.
- Fee rules created in the platform UI are platform-wide (`organization_id` null). The resolver can prefer an organization rule, and there is no screen to create one.
- USD only. One fee label. The commercial terms are not signed off ([DECISIONS.md](DECISIONS.md)).
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
- There is no deploy workflow and no hosting secret in the repo.
- Node and Vite are not part of the app. An upload that contains `node_modules` has that directory removed by the release script.
