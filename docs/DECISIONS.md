# Decisions that need Michael's approval

Date recorded: 2026-10-26. Nothing in this table is a signed commercial or legal decision. The code uses the recommendation only so the engine and the tests have one behavior. Changing a row is a product change, not a silent default.

| ID | Question | Options | Recommendation in this phase | Approval |
|---|---|---|---|---|
| D1 | Who pays the booking fee? | A line on the buyer's order, or the organizer absorbs it | Show it as a buyer-facing line labeled "Booking fee" once per order. The label is a platform setting. | Open |
| D2 | Does a ticket refund return the booking fee? | Full fee back, proportional fee back, or never | Do not automatically refund the platform fee. The organizer refunds the ticket through the provider. The fee ledger is a separate decision. | Open |
| D3 | Free orders | Charge the minimum, or charge nothing | Eligible ticket subtotal of 0 cents charges 0. The minimum is not applied. | Open |
| D4 | When does the minimum apply? | Always, or only when the eligible subtotal is above 0 | Only when the eligible subtotal is above 0. | Open |
| D5 | Currency | USD only, or multi-currency | USD only. `FeeRuleData` rejects anything else. | Open |
| D6 | Which orders pick up a new rate? | All open orders, or new orders only | New orders only. Snapshots are immutable. | Open |
| D7 | Launch rate | 3 percent, minimum $1, maximum $5, or another schedule | 300 basis points, minimum 100 cents, maximum 500 cents, effective 2026-01-01 00:00:00 UTC. | Open |
| D8 | Rounding | Half-up, half-even, or truncate | Half-up on the remainder against 10000, documented in [FEES.md](FEES.md). | Open |
| D9 | Is the booking fee taxable? | Included in the tax basis, taxed on its own, or excluded | Excluded from the fee basis. Phase 1 does not tax the fee. | Open. Needs accounting review. |
| D10 | Cash and box-office orders | Assess a fee the organizer owes, or no platform fee | Not implemented. The ledger can represent `owed` later. | Open |
| D11 | Authorize.net remittance | Per order, weekly invoice, or monthly invoice | Invoice or receivable. No split is assumed. Cadence is not built. | Open |
| D12 | Support admin and fees | View and edit, or view only | `platform_support_admin` can view fee rules and cannot write them. | Open |
| D13 | Who may decide a refund? | Owner only, owner and finance, or any admin | Owner and finance have `finance.refunds.decide`. Org admin can view finance and cannot decide. | Open |
| D14 | Partial refunds | Allow before the provider confirms, or only after | Submit does not complete. Confirmation requires the provider reference. Partial amounts are a later phase. | Open |
| D15 | Second organization owner | Invitation may grant `org_owner`, or ownership transfer is separate | Invitations cannot grant `org_owner`. | Open |
| D16 | Provider order | Stripe and Authorize.net together, or Stripe first | Stripe Connect first. Authorize.net later, same `PaymentGateway` interface. | Open, recommended |
| D17 | Stripe account type and charge type | Standard, Express, or Custom; direct, destination, or separate charges and transfers | Standard accounts and direct charges with `application_fee_amount`. See [PAYMENTS.md](PAYMENTS.md). | Open |
| D18 | Wording and disclosures | "Booking fee" or another name; who the merchant of record is | The string "Booking fee" is a setting. Legal review still has to approve the buyer-facing wording and the merchant of record. | Open. Needs legal review. |

Also still open, and not a fee policy: SiteGround plan, cron, wildcard DNS, and wildcard SSL for this domain. Those are checks in [HOSTING.md](HOSTING.md), not product choices the code can settle.
