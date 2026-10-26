# Security checklist

Phase 1 controls that are in the code:

- [x] Hostname resolves the tenant. A client organization id that does not match is 403.
- [x] Tenant queries use `OrganizationScope`. No context matches nothing (`where 0 = 1`), except platform fee defaults.
- [x] Cross-organization sign-in does not start a session (403). Bad credentials are 422.
- [x] Email verification is required before organization or platform pages.
- [x] Password reset tokens expire in 60 minutes and are not created for an email that cannot sign in on that host.
- [x] Reset and invitation tokens are compared with a length check and `hash_equals`. Invitation tokens are stored as SHA-256 only.
- [x] Login regenerates the session id and CSRF token. Logout invalidates the session.
- [x] CSRF is enabled. Production cookies are `Secure`, `HttpOnly`, and `SameSite=lax`.
- [x] Login, registration, and password-email routes are limited to 5 requests per minute.
- [x] Passwords require 12 characters with upper, lower, and a number. The hashed cast stores them hashed.
- [x] `public/.htaccess` denies PHP except `index.php` and disables indexes.
- [x] `.gitignore` ignores `.env` and `.env.*` except `.env.example`. The example file has empty secret fields.
- [x] Audit logs redact property keys matching password, secret, token, card, cvc, cvv, or authorization.
- [x] Fee snapshots and ledger rows throw if updated. Refund completion requires a matching provider reference.
- [x] Stripe is not called. Authorize.net accepts an Accept.js nonce, rejects posted card fields, encrypts the transaction key and signature key, and verifies webhook HMAC-SHA512. Live mode is off by default. The fake processor is refused outside tests.
- [x] `worxevents:hosting-check` and `worxevents:platform-admin` do not print passwords.
- [x] Pagination cannot request more than 100 rows.

Phase 7 review of the code in this tree. This is not a penetration test.

- [x] Inventory reservation, seat hold, and door check-in take `lockForUpdate` inside a transaction. A second connection is expected to wait. See `tests/Feature/Readiness/ConcurrencyTest.php`.
- [x] Issued ticket tokens are stored as SHA-256. Audit redaction includes keys named token.
- [x] Checkout rejects posted card numbers. The transaction key and signature key use the encrypted cast. The platform payments page does not select those columns.
- [x] The failed-jobs page selects `uuid`, `queue`, and `failed_at`. It does not select `payload` or `exception`.
- [x] The analytics script is emitted only for an https hook and only after the consent cookie is set.
- [x] Announcements go only to consented addresses that have not unsubscribed. The unsubscribe URL is a signed route.
- [x] Promo QR markup comes from the QR library for an event URL this app builds. It is not request HTML.
- [x] Sign-in failures record a kind, the message "Sign-in was rejected.", and a SHA-256 of the IP. They do not store the password.

Still required outside the repository:

- [ ] `APP_KEY`, database password, `MAIL_PASSWORD`, and future Stripe secrets only in `laravel/shared/.env`
- [ ] `APP_DEBUG=false` and `APP_ENV=production` on the server
- [x] Production HTTP redirects to HTTPS in the application. SiteGround HTTPS Enforce stays off. Session cookies are `Secure` in production.
- [ ] Database user is not a superuser
- [x] The deploy runs `worxevents:platform-admin` for the first platform user. It does not print a password or a reset URL. Public registration does not create that user.
- [ ] `config/organizations.php` reviewed before sync. It is empty in git
- [ ] Webhook signing secrets, when Stripe is enabled, checked before any `collected` ledger row
- [ ] Dependency updates: CI runs `composer audit`
- [ ] Legal review of the buyer-facing fee wording and merchant of record ([DECISIONS.md](DECISIONS.md) D18)
- [ ] Accounting review of fee tax treatment (D9)

Reporting: send security reports to the address Michael publishes for the domain. Do not open a public issue that includes a live credential or customer export.
