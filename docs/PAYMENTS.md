# Payments

`PaymentGateway` exposes `name()`, `isReady()`, and `refund()`. Charge, lookup, and connection checks live on `AuthorizeNetGateway`, not on the shared interface, so the Stripe adapter does not grow a charge method. `stripe/stripe-php` is not installed.

`PAYMENTS_GATEWAY=fake` exists for tests. `PaymentGatewayManager` throws if `fake` is used outside the testing environment.

## Recommendation

Paid checkout uses Authorize.net now. Stripe stays the disabled stub. [Decision D16](DECISIONS.md) still says Stripe first and is open; the build order follows the later instruction to ship Authorize.net and leave Stripe for later. `PAYMENTS_GATEWAY` still defaults to `stripe` for the refund provider name. Organizers who validate Authorize.net credentials charge through that adapter regardless of the default.

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

Researched from Authorize.net Accept.js documentation on 2026-10-26. Implemented as sandbox-only paid checkout.

- The organizer uses their own merchant account. There is no split and no application fee.
- Accept.js posts card data from the browser to Authorize.net and returns a one-time nonce (`dataDescriptor` + `dataValue`). The server sends that nonce in `createTransactionRequest`. A posted card number, CVV, or expiry is rejected. The public client key and API login ID are on the event page because Accept.js requires them. The transaction key and signature key are not.
- Credentials are encrypted at rest on `payment_settings`. Save runs `authenticateTestRequest` first. A failed check stores nothing. The settings screen does not print the keys back.
- Sandbox calls `https://apitest.authorize.net`. Live mode throws `GatewayDisabled` unless `AUTHORIZE_NET_LIVE` is true. The event page loads `https://jstest.authorize.net/v1/Accept.js` only for a validated sandbox account.
- The server prices the order. A $50 ticket is a $1.50 booking fee and a $51.50 charge. The browser total is not trusted.
- Tickets are issued only when the processor result is accepted, `status` is `approved`, and a transaction id is present. `ProviderResult.completed` stays false. A decline cancels the order and releases the hold. A lost response leaves the order pending; a signed webhook or a later lookup can settle it once.
- Webhooks are `POST /webhooks/authorize-net/{organization}` and are excluded from CSRF. The `X-ANET-Signature` header is HMAC-SHA512 of the raw body using the organizer's signature key. The notification id is stored once. A repeat does not issue a second ticket.
- Tests bind `FakeAuthorizeNetClient`. The HTTP client is covered with `Http::fake` and does not call Authorize.net.

The booking fee is a receivable. A paid order posts `assessed` and `owed`. `collected` stays empty because there is no split. Finance opens an invoice for the outstanding balance and marks it paid with a payment reference. That posts one `remitted` row per order (`{organization}:{order}:remitted`). Doing it again with the same reference is a no-op. A different reference is refused. Automatic weekly or monthly cadence is still not built ([decision D11](DECISIONS.md)).

## Refunds

The organizer decides. The provider confirms. `OrganizerRefundWorkflow::request` stores status `requested`. `submit` may move it to `submitted` or `failed` and always leaves `completed_at` null. `confirm` is the only writer of `completed`, and only when the stored provider reference matches.

`POST /finance/refunds/{refund}/confirm` is that confirmation for Authorize.net. It looks up the refund transaction and calls `confirm` only when the lookup is approved. It does not mark a refund complete from the organizer's decision. Stripe and the unconfigured gateway still return HTTP 409 and leave the row `requested`. There is no route that completes a Stripe refund.

A partial refund leaves the tickets issued. Once completed refunds cover the ticket subtotal, tickets are voided. The booking fee stays `owed` ([decision D2](DECISIONS.md)). The refund amount cannot exceed the remaining charge. The fake gateway used by the Phase 1 test still cannot be confirmed from this route.
