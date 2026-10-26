# Payments

Phase 1 does not charge a card and does not install `stripe/stripe-php`. `PaymentGateway` exposes `name()`, `isReady()`, and `refund()`. Checkout is not on the interface, so this phase cannot pretend to take payment.

`PAYMENTS_GATEWAY=fake` exists for tests. `PaymentGatewayManager` throws if `fake` is used outside the testing environment.

## Recommendation

Ship Stripe first. Add Authorize.net later behind the same interface. That split is [decision D16](DECISIONS.md) and still needs Michael's approval. The adapters match the recommendation so the boundary is real: Stripe is the default name, and Authorize.net `isReady()` is always false.

## Stripe Connect

Researched 2026-10-26 from Stripe's direct-charges guide: https://docs.stripe.com/connect/direct-charges

Recommended shape, not yet called:

- The organizer connects a Stripe **Standard** account and keeps the Stripe Dashboard.
- The charge is a **direct charge** on that connected account (`Stripe-Account` header). The payment appears on the organizer's account.
- The booking fee is `application_fee_amount` on that charge, in cents, positive, and less than the charge amount. Stripe moves that fee to the platform balance.
- Express and Custom are a worse Phase 1 fit because the organizer would not have the full Dashboard.
- Destination charges, and separate charges plus transfers, are a worse fit because the platform would own the charge. The organizer is the merchant for the ticket.
- A browser return is not proof of payment. The platform records `collected` only after an `application_fee.created` webhook signed with `STRIPE_WEBHOOK_SECRET`.
- Application fees are created asynchronously. Refunds are not automatic. `refund_application_fee=true` returns the whole fee on a full refund. A partial refund returns a proportional fee unless the platform refunds the fee itself. Phase 1 does not do either. See [decision D2](DECISIONS.md).

`StripeConnectGateway::isReady()` is true only when `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` are both non-empty. Those values stay in the server's `.env`, never in git. If they are empty, `refund()` throws `GatewayUnconfigured`. If they are set, `refund()` still throws `GatewayDisabled` and sends no HTTP request. The finance screen maps both exceptions to HTTP 409 and leaves the refund `requested`.

## Authorize.net

Researched from Authorize.net Accept.js documentation on 2026-10-26.

- The organizer uses their own merchant account. This platform does not assume split payments or an application fee.
- Accept.js posts card data from the browser to Authorize.net and returns a one-time nonce. The server would send that nonce in `createTransactionRequest`. The public client key can be in a page. The API login id and transaction key cannot.
- Accept.js has two integration styles: a form the application hosts (SAQ A-EP) or a hosted payment form (SAQ A). The hosted form is the smaller PCI surface when this phase is built.
- The booking fee is a receivable. The ledger posts `owed` when the order is assessed for an Authorize.net organizer, and `remitted` when an invoice payment is recorded. Cadence is [decision D11](DECISIONS.md).
- `AuthorizeNetGateway` accepts no card data, `isReady()` is false, and `refund()` throws `GatewayDisabled`.

## Refunds

The organizer decides. The provider confirms. `OrganizerRefundWorkflow::request` stores status `requested`. `submit` may move it to `submitted` or `failed` and always leaves `completed_at` null. `confirm` is the only writer of `completed`, and only when the stored provider reference matches. No route calls `confirm`. A mismatched reference leaves the row `submitted`.
