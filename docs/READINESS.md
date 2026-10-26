# Production readiness

This report describes the tree on the phase 7 branch. It is not a statement that `app.worxevents.com` is serving traffic.

## What is implemented

- Multi-tenant organizations, roles, audit log, and the booking-fee engine. Michael approved the fee and refund choices in [DECISIONS.md](DECISIONS.md) on 2026-10-26. D9 and D18 remain external reviews. D15, D16, and D17 stay open.
- Free registration, paid Authorize.net sandbox checkout, refunds, fee invoices, check-in, box office, reserved seats without a visual designer, discounts, add-ons, waitlists, transfers, and the platform admin screens in [ADMIN.md](ADMIN.md).
- Stripe is a disabled adapter. `stripe/stripe-php` is not installed. Authorize.net live mode is off unless `AUTHORIZE_NET_LIVE` is true.
- CI is Pint, PHPStan level 5, `composer audit`, and PHPUnit on MySQL 8.4 and MariaDB 11.8. The deploy workflow runs as soon as CI succeeds on a push to `main`. There is no manual approval gate.

## What was exercised here

On this machine, `php artisan test --compact` reported 96 passed and 638 assertions. Pint and PHPStan level 5 reported no remaining issues. The database was MySQL 8.0.46 and the CLI was PHP 8.3.6. The MySQL 8.4 and MariaDB 11.8 jobs in CI were not run here because Docker is not available. Do not treat this report as those CI jobs passing.

Concurrency tests hold the inventory, seat, and check-in row locks and expect a second connection to time out. An accessibility check covers the skip link, `lang`, named controls, the accessible-seat label, focus styles, and `prefers-reduced-motion`. It is not a formal WCAG audit.

## Before production

Michael, or whoever operates the host, still has to:

1. Create the SiteGround site for `app.worxevents.com` if it does not exist. Set `WORXEVENTS_BASE` if the directory is not `~/www/app.worxevents.com`.
2. Add `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` as Actions secrets if they are not already there. The documented SSH secrets are `SG_SSH_HOST`, `SG_SSH_USER`, `SG_SSH_PORT`, `SG_SSH_KEY`, and `SG_SSH_PASSPHRASE`.
3. Schedule `php artisan schedule:run` every minute, force HTTPS, and set SMTP. Until SMTP is set, mail is logged.
4. Run `worxevents:platform-admin` for the first platform user.
5. Finish the legal and accounting reviews (D9, D18) before the public legal pages are treated as final. Those pages say they are not legal advice.
6. Leave Stripe and Authorize.net live mode off until you choose to configure them.

## Known limitations

See [LIMITATIONS.md](LIMITATIONS.md). The ones that matter for a first deploy: no visual seat designer, no bundled QR camera decoder, box-office card is not an Authorize.net charge, fee invoices are on demand rather than a weekly schedule, analytics is an opt-in hook with an empty default, and plans do not bill anyone.
