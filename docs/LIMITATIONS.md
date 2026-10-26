# Known limitations

Phase 3 adds Authorize.net sandbox checkout, refunds, and fee remittance. These are still absent on purpose.

- Stripe is not called and `stripe/stripe-php` is not installed. `StripeConnectGateway` stays disabled.
- Authorize.net live mode is off unless `AUTHORIZE_NET_LIVE` is true. Tests use a fake client and `Http::fake`. They do not create real charges.
- The booking fee is invoiced on demand. A weekly or monthly remittance schedule is not built.
- `collected` is not posted. Authorize.net does not split the booking fee out of the charge.
- Door scanning uses the browser BarcodeDetector. Browsers without it use the typed ticket code. A bundled QR decoder is not shipped.
- Reserved seating has no visual designer. Organizers edit sections, rows, and seats. Buyers use a labeled list. Accessible seats say "(accessible)" in the label.
- A public seat hold expires on the reservation schedule. A seat attached to a pending card order stays held until that order is approved or declined.
- Box office cash and card are records of money taken at the door. They are not sent to Authorize.net. Comps are $0. The booking fee is not assessed on those orders (decision D10).
- Ticket delivery is an SVG QR code and a one-page PDF drawn from the QR matrix. PNG QR codes are not generated, because the app does not require GD.
- The raw ticket token is only in the email attachments. The database stores its SHA-256. A failed send can be replaced from the order; the old token is voided.
- Stripe still has no webhook and no route that marks a Stripe refund complete. Authorize.net webhooks are signature-checked, and the confirm route looks up the refund before it completes.
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
- Analytics does not load unless the visitor opts in and `WORXEVENTS_ANALYTICS_HOOK` is an https URL. The default is empty.
- Announcements go only to addresses that checked the consent box and have not unsubscribed. The list is capped at 100 recipients per send.
- Platform plans are labels. They do not charge a card.
- A formal WCAG audit has not been run. Tokens and targets are aimed at WCAG 2.2 AA. The phase 7 check covers the skip link, `lang`, named controls, the accessible-seat label, focus styles, and `prefers-reduced-motion`.
- A penetration test has not been run. The phase 7 notes in [SECURITY.md](SECURITY.md) are a review of the code that is already in the tree.
- SiteGround for this domain has not been measured ([HOSTING.md](HOSTING.md)).
- CI is written for MySQL 8.4 and MariaDB 11.8. The machine that produced this phase ran PHPUnit on MySQL 8.0.46 only. Docker was not available here, so those two engines were not executed in this run.
- Deploy reads SiteGround and database credentials from GitHub Actions secrets. Those values are not in the repo. The workflow does not call Stripe.
- Node and Vite are not part of the app. An upload that contains `node_modules` has that directory removed by the release script.
