# Backlog

Phase 1 is this repository's current scope. The order below is the recommended build order. Michael approved the fee and refund rows in [DECISIONS.md](DECISIONS.md) on 2026-10-26. Tax treatment, buyer-facing wording, and merchant of record are still open.

## Phase 2 — Catalog and free registration

Shipped on this branch: organizer setup, venues, events, sessions, categories, tags, ticket types, expiring inventory holds, free guest checkout, attendees, hashed ticket tokens with SVG QR and PDF email, the public site, sitemap, Open Graph, Event structured data, draft `noindex`, and basic reports. A free order records a $0 booking-fee snapshot.

## Phase 3 — Authorize.net paid ticketing

Shipped on this branch: Accept.js sandbox checkout, encrypted credentials, connection check, signed idempotent webhooks, transaction lookup, partial and full refunds, server-side totals with a booking-fee line, tickets only after approval, `owed` and `remitted` through an on-demand invoice, and a financial CSV. Live mode stays off. Stripe remains the disabled stub. Tests mock the processor.

## Phase 4 — Event day

Shipped on this branch: mobile check-in with BarcodeDetector or a typed code, manual lookup, locked check-in, duplicate / wrong-event / refunded / canceled results, reversal for `events.manage`, box office walk-up with cash, card records, and comps, shared inventory, and an attendee CSV. A bundled camera decoder is not included.

## Phase 5 — Advanced catalog

Shipped on this branch: daily and weekly timed-entry sessions, reserved seats (plan, section, row, seat, accessible seats, price zones, expiring holds), an accessible seat list, discounts (percent and fixed, limits, expiry, ticket eligibility), add-ons excluded from the booking fee, waitlist join and offer, ticket transfers that void the old token, and advanced report totals. A visual seat designer is not included. Organizers edit sections, rows, and seats. Buyers pick from a labeled list.

## Phase 6 — Growth and platform admin

Shipped on this branch: platform screens for users and roles, suspend and reinstate, plans, payment oversight without secrets, disputes, audit logs, queued and failed jobs, security events, and a database health check. Support cases, referral and UTM values on the order, a promo QR for an event link, announcements only to consented addresses with an unsubscribe link, and an analytics script that loads only after consent and only when an https hook is configured.

## Phase 7 — Readiness

Shipped on this branch: a code security review recorded in [SECURITY.md](SECURITY.md), concurrency tests that hold the inventory, seat, and check-in row locks, an accessibility check of skip links, labels, the accessible-seat text, focus styles, and reduced motion, and the operator manuals [ADMIN.md](ADMIN.md), [ORGANIZER.md](ORGANIZER.md), [TROUBLESHOOTING.md](TROUBLESHOOTING.md), [BACKUP.md](BACKUP.md), [UPDATE.md](UPDATE.md), and [READINESS.md](READINESS.md). Known limits stay in [LIMITATIONS.md](LIMITATIONS.md). This is not a penetration test or a formal WCAG audit.

## Hardening that can land beside any phase

- Run `worxevents:hosting-check` on the real host and fill in [HOSTING.md](HOSTING.md).
- Add a deploy workflow only after GitHub secrets exist outside the repo.
- Per-organization fee rules in the UI.
- Queue the mail once cron is proven.
- Replace the synchronous auth mail only after a failed job is visible to support.
