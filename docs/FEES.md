# Booking fee

One fee per order:

```text
eligible = max(0, ticket_subtotal_cents - discount_cents)
if eligible == 0: fee = 0
else: fee = min(maximum, max(minimum, percent(eligible)))
```

Merchandise, donations, add-ons, and tax are stored on the snapshot and are not part of `eligible`. The fee is pre-tax. Several tickets are summed first, then one percent is applied.

## Integer rounding

All amounts are integer cents. There is no floating point in `MoneyFormat` or `PercentOfCents`.

```text
numerator = amount_cents * basis_points
fee = intdiv(numerator, 10000)
if numerator % 10000 >= 5000: fee = fee + 1
```

100 basis points is 1 percent. 300 basis points is 3 percent. A remainder of exactly 5000 (half a cent) rounds away from zero. `PercentOfCents` rejects a product that would overflow `PHP_INT_MAX`.

## Launch rule

Seeded as a platform rule (`organization_id` null), effective 2026-01-01 00:00:00 UTC:

| Field | Value |
|---|---|
| Percent | 300 bps (3%) |
| Minimum | 100 cents |
| Maximum | 500 cents |
| Currency | USD |

An organization-specific rule wins over the platform rule when `effective_at` is at or before the order time. Ties break by latest `effective_at`, then highest id. Creating a rule inserts a new row. Old rows stay. Existing snapshots are not rewritten.

The minimum and maximum are commercial choices waiting on [DECISIONS.md](DECISIONS.md). The code and the tests use this schedule so the math is fixed.

## Examples the tests lock

| Eligible ticket subtotal | Fee |
|---|---|
| $10.00 | $1.00 |
| $20.00 | $1.00 |
| $33.33 | $1.00 |
| $50.00 | $1.50 |
| $100.00 | $3.00 |
| $166.67 | $5.00 |
| $200.00 | $5.00 |
| $500.00 | $5.00 |
| $0.00 | $0.00 |

$33.33 is 3333 cents. `3333 * 300 = 999900`. `999900 / 10000` is 99 with remainder 9900, which rounds to 100 cents, and the minimum is also 100. $166.67 is 16667 cents. `16667 * 300 = 5000100`, which is 500 cents and a remainder of 100, then the maximum holds it at 500.

Also locked: two $25 tickets are one $1.50 fee, not two $1.00 fees. A $100 ticket subtotal with a $50 discount and ignored merchandise, donation, add-on, and tax is a $1.50 fee. A discount that brings tickets to zero is free even though the minimum is $1. A custom rule can change the percent, minimum, and maximum.

## Snapshots and the ledger

`BookingFeeRecorder` is idempotent. The same organization, order key, and basis returns the original snapshot and does not add another `assessed` row. A second call with a different basis throws `IdempotencyConflict`. Models using `ImmutableRecord` throw if `save` is called on an existing row.

Ledger kinds, summed separately:

| Kind | Meaning | Phase 1 |
|---|---|---|
| `assessed` | Fee priced for the order | Written with the snapshot. Idempotency key `{organization}:{order}:assessed`. |
| `collected` | Platform actually received the fee | Not posted. Reserved for a Stripe `application_fee` webhook. |
| `owed` | Organizer still owes the fee | Not posted. Reserved for Authorize.net receivables. |
| `remitted` | Organizer paid an invoice for `owed` | Not posted. |

`postLedger` is idempotent on `idempotency_key`. A reused key with a different organization, order, kind, or amount is refused.
