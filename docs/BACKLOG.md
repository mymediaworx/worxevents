# Backlog

Phase 1 is this repository's current scope. The order below is the recommended build order. Michael approved the fee and refund rows in [DECISIONS.md](DECISIONS.md) on 2026-10-26. Tax treatment, buyer-facing wording, and merchant of record are still open.

## Phase 2 — Catalog and free registration

Shipped on this branch: organizer setup, venues, events, sessions, categories, tags, ticket types, expiring inventory holds, free guest checkout, attendees, hashed ticket tokens with SVG QR and PDF email, the public site, sitemap, Open Graph, Event structured data, draft `noindex`, and basic reports. A free order records a $0 booking-fee snapshot.

## Phase 3 — Authorize.net paid ticketing

Shipped on this branch: Accept.js sandbox checkout, encrypted credentials, connection check, signed idempotent webhooks, transaction lookup, partial and full refunds, server-side totals with a booking-fee line, tickets only after approval, `owed` and `remitted` through an on-demand invoice, and a financial CSV. Live mode stays off. Stripe remains the disabled stub. Tests mock the processor.

## Phase 4 — Event day

Mobile check-in, camera QR scanning, manual lookup, atomic check-in, duplicate and wrong-event outcomes, reversal with permission, box office walk-up, comps, cash and card records, shared inventory, and attendee exports.

## Phase 5 — Advanced catalog

Recurring and timed-entry sessions, reserved seating, discounts, add-ons, waitlists, ticket transfers that invalidate the old token, and advanced reports. A visual seat designer can be deferred if the seat data and an accessible picker ship first.

## Phase 6 — Growth and platform admin

Platform admin for organizations, users, roles, suspension, fees, plans, payment oversight, disputes, audit logs, jobs, security events, and health. Support cases, referral and UTM tracking, promo QR codes, consented announcements, and analytics hooks behind consent.

## Phase 7 — Readiness

Security review, concurrency tests for inventory, seats, and check-in, an accessibility pass, and the operator manuals: admin, organizer, troubleshooting, backup and restore, update and rollback, production readiness, and known limitations.

## Hardening that can land beside any phase

- Run `worxevents:hosting-check` on the real host and fill in [HOSTING.md](HOSTING.md).
- Add a deploy workflow only after GitHub secrets exist outside the repo.
- Per-organization fee rules in the UI.
- Queue the mail once cron is proven.
- Replace the synchronous auth mail only after a failed job is visible to support.
