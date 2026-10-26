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
- [x] Stripe is not called. Authorize.net accepts no card data. The fake gateway is refused outside tests.
- [x] `worxevents:hosting-check` and `worxevents:platform-admin` do not print passwords.
- [x] Pagination cannot request more than 100 rows.

Still required outside the repository:

- [ ] `APP_KEY`, database password, `MAIL_PASSWORD`, and future Stripe secrets only in `laravel/shared/.env`
- [ ] `APP_DEBUG=false` and `APP_ENV=production` on the server
- [ ] HTTPS forced at the host
- [ ] Database user is not a superuser
- [ ] First platform admin created with `worxevents:platform-admin` over SSH, not by public registration
- [ ] `config/organizations.php` reviewed before sync. It is empty in git
- [ ] Webhook signing secrets, when Stripe is enabled, checked before any `collected` ledger row
- [ ] Dependency updates: CI runs `composer audit`
- [ ] Legal review of the buyer-facing fee wording and merchant of record ([DECISIONS.md](DECISIONS.md) D18)
- [ ] Accounting review of fee tax treatment (D9)

Reporting: send security reports to the address Michael publishes for the domain. Do not open a public issue that includes a live credential or customer export.
