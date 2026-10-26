# Platform administrator manual

Sign in on a platform host (`app.worxevents.com` in production, `app.worxevents.test` in the test configuration). The account needs a row in `platform_members`. Create the first one over SSH:

```bash
php artisan worxevents:platform-admin you@example.com
```

The command emails a reset link for a new user. It does not print a password.

## What each screen does

| Screen | Permission | Use |
|---|---|---|
| Organizations | Platform member | See status. Suspend or reinstate requires `platform.organizations.manage`. |
| Users | `platform.organizations.view` | Emails, organization roles, and platform roles. |
| Fee rules | `platform.fees.view` | Read the booking-fee rule. Writing requires `platform.fees.manage`. A new rule applies to new orders only. |
| Plans | `platform.organizations.view` | Names and prices. Saving requires `platform.settings.manage`. A plan does not charge a card. |
| Payments | `platform.fees.view` | Which organizations validated Authorize.net, and whether the environment is sandbox. Transaction keys are not shown. |
| Disputes | `platform.organizations.view` | Open a case and mark it open, won, or lost. This does not move money. |
| Support | `platform.organizations.view` | Cases opened by organizations. Closing requires `platform.organizations.manage`. |
| Audit | `platform.audit.view` | Recent actions. Property values for secrets are redacted. |
| Jobs | `platform.audit.view` | How many jobs are queued, and the id of failed jobs. Payloads and stack traces are not shown. |
| Security | `platform.audit.view` | Rejected sign-ins. The password is not stored. |
| Health | `platform.audit.view` | `select 1` against the database. HTTP 503 when that fails. |

`platform_support_admin` can view organizations, fees, and the audit log. It cannot suspend an organization, write a fee rule, or save a plan.

## Suspension

Suspending an organization returns 403 on its hostname. Reinstating sets the status back to `active`. Termination is not a button on this screen.

## Fees and refunds

Fee and refund policy is in [DECISIONS.md](DECISIONS.md). Michael approved the fee and refund rows on 2026-10-26. Tax treatment (D9) and buyer-facing wording / merchant of record (D18) are still external reviews.

Authorize.net live mode stays off until `AUTHORIZE_NET_LIVE=true`. Stripe is not connected.
