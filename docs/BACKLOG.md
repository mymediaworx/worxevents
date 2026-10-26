# Backlog

Phase 1 is this repository's current scope. The order below is the recommended build order. Fee and refund policy items in [DECISIONS.md](DECISIONS.md) can change the details.

## Phase 2 — Catalog and the public event

Events, venues, ticket types, inventory, holds with a short expiry, and the public event page. Prices are integer cents and are copied onto a snapshot at order time. The booking-fee recorder runs once per order on the discounted ticket subtotal.

## Phase 3 — Stripe Connect

Organizer onboarding to a Standard account, direct charges, `application_fee_amount`, Checkout or the Payment Element, and signed webhooks. `collected` is posted only from `application_fee.created`. Refund submission becomes a real Stripe call and still does not complete until confirmation. Sandbox keys live in `shared/.env`.

## Phase 4 — Authorize.net

Accept.js hosted payment form on the organizer's merchant account. No split. The fee posts as `owed`, then `remitted` when the invoice is paid. The same `PaymentGateway` interface grows a charge method. Stripe stays the first implementation.

## Phase 5 — Door and box office

Box-office sales, check-in, QR validation, and a bounded offline queue that syncs through `background_jobs`. Staff roles already exist: `box_office` and `check_in_staff`.

## Phase 6 — Money operations

Refunds end to end, including the provider confirmation step and the booking-fee policy Michael approves. Finance reports and a reconciliation of assessed, collected, owed, and remitted.

## Phase 7 — Buyer account and distribution

Buyer order history beyond the shell, transfers, waitlists, embeds, and custom domains that are verified instead of trusted from config. Legal disclosures for the fee and the merchant of record ship with this, or earlier if counsel requires them before the first charge.

## Hardening that can land beside any phase

- Run `worxevents:hosting-check` on the real host and fill in [HOSTING.md](HOSTING.md).
- Add a deploy workflow only after GitHub secrets exist outside the repo.
- Per-organization fee rules in the UI.
- Queue the mail once cron is proven.
- Replace the synchronous auth mail only after a failed job is visible to support.
