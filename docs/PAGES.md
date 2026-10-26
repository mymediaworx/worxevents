# Pages and design system

Routes are server-rendered. A shell page still checks the permission. Livewire is used for the team roster only.

| Route | Who | What Phase 1 shows |
|---|---|---|
| `GET /` on a platform host | Public | Marketing home and upcoming published events |
| `GET /` on an organization host | Public | Organization name and published events |
| `GET /design` | Public | Design tokens |
| `GET/POST /login`, `/register` | Guest | Tenant-bound sign-in and buyer registration |
| `GET/POST /forgot-password`, `/reset-password` | Guest | Expiring reset |
| `GET /email/verify` | Signed-in | Verification notice |
| `GET /invitations/{token}` | Public | Invitation. Accept requires auth and a verified email |
| `GET /dashboard` | Organization member, verified | Home for that organization |
| `GET /events` | `events.view` | Event list. Create, edit, publish, sessions, ticket types, and questions require `events.manage` |
| `GET/POST /box-office` | `tickets.sell` | Walk-up cash, card record, or comp. Shared inventory |
| `GET /check-in` | `checkin.scan` | Door scan, camera detector, and lookup |
| `POST /check-in/tickets/{ticket}/reverse` | `events.manage` | Undo a check-in |
| `GET /events/{event}/attendees.csv` | `events.view` | Attendee export, 100 rows, no ticket tokens |
| `GET /tickets` | `orders.view_own` | The signed-in buyer's registrations on this organization |
| `GET /team`, `POST /team/invitations` | View, then manage | Roster and invitations |
| `GET /finance` | `finance.view` | Refund list |
| `POST /finance/refunds` | `finance.refunds.decide` | Request a refund |
| `POST /finance/refunds/{refund}/submit` | `finance.refunds.decide` | Submit to the provider. Stripe stays unconfigured and returns 409 |
| `POST /finance/refunds/{refund}/confirm` | `finance.refunds.decide` | Authorize.net lookup, then confirm. Does not complete a Stripe refund |
| `GET /payments`, `POST /payments` | `organization.settings.update` | Authorize.net sandbox credentials. Write-only secrets |
| `GET /platform` | Platform member | Organizations, with suspend and reinstate for `platform.organizations.manage` |
| `GET /platform/users` | `platform.organizations.view` | Users, organization roles, and platform roles |
| `GET /platform/plans` | `platform.organizations.view` | Plans. Writing requires `platform.settings.manage` and does not charge a card |
| `GET /platform/payments` | `platform.fees.view` | Processor environment and validation. Secrets are not shown |
| `GET /platform/disputes` | `platform.organizations.view` | Disputes. Opening and updating require `platform.organizations.manage` |
| `GET /platform/audit` | `platform.audit.view` | Audit log |
| `GET /platform/jobs` | `platform.audit.view` | Queue depth and failed job ids. Payloads and exceptions are not shown |
| `GET /platform/security` | `platform.audit.view` | Sign-in failures, without the password |
| `GET /platform/health` | `platform.audit.view` | Database check |
| `GET /platform/support` | `platform.organizations.view` | Support cases from organizations |
| `GET /support` | `organization.view` | Open a support case |
| `GET /announcements` | `organization.settings.update` | Email opted-in addresses. Unsubscribe is a signed link |
| `GET /events/{event}/promo` | `events.manage` | QR code for the event link plus a referral code |
| `GET/POST /platform/fees` | View, then manage | Immutable platform fee rules |
| `GET /setup` | `organization.settings.update` | Organization details, logo, currency, and time zone |
| `GET /venues` | `events.view` | Venues. Writing requires `events.manage` |
| `GET /reports` | `finance.view` | Counts, advanced totals, fee ledger, and remittance invoices |
| `GET /events/{event}/extras` | `events.manage` | Series, discounts, add-ons, seat data, waitlist offers, transfers. No visual seat designer |
| `GET /reports/finance.csv` | `finance.view` | Financial CSV |
| `POST /reports/remittance` | `finance.refunds.decide` | Invoice outstanding booking fees |
| `POST /reports/remittance/{invoice}/paid` | `finance.refunds.decide` | Mark an invoice paid and post `remitted` |
| `GET /browse`, `/categories/{slug}` | Public | Search and filter published events. Drafts are omitted |
| `GET /e/{slug}` | Public | Event page, free registration or Accept.js checkout, discount code, add-ons, accessible seat list, and a short inventory hold. Drafts are 404 |
| `POST /e/{slug}/waitlist` | Public | Join the event waitlist. One row per email |
| `POST /tickets/{ticket}/transfer` | `events.manage` | Void the old token and email a new one |
| `POST /webhooks/authorize-net/{organization}` | Public, signed | Authorize.net notification. No CSRF. No card data |
| `GET /o/{organization}` | Public | Organizer page |
| `GET /v/{venue}`, `/o/{organization}/v/{venue}` | Public | Venue page |
| `GET /about`, `/pricing`, `/help`, `/contact` | Public | Marketing and contact |
| `GET /legal/terms`, `/legal/privacy`, `/legal/refunds` | Public | Draft legal text, marked as not legal advice |
| `GET /accessibility` | Public | Accessibility statement |
| `GET /sitemap.xml` | Public | Published events and public pages. Drafts are excluded |
| `GET /registration/{reference}` | Public | Email lookup. `noindex`. Does not show ticket tokens |
| `GET /up` | Public | Laravel health. Not host-gated |

Error views: 403, 404, 419, 429.

## Design

Mobile-first. The visual target is the BrandWorx staff shell used by ReserveWorx: a white app bar, grey page, rounded white cards, a sidebar from 1024px, and a bottom tab bar on smaller screens. Plain CSS in `public/css/worxevents.css`. No Node build.

| Token | Value | Use |
|---|---|---|
| `--page` | `#F3F4F6` | Page background |
| `--surface` | `#FFFFFF` | Cards and app bar |
| `--ink` | `#1F2937` | Text |
| `--muted` | `#4B5563` | Secondary text, darkened so it clears WCAG AA on the page |
| `--brand` | `#0F5C4D` | Primary actions, avatar, links |
| `--accent` | `#F59E0B` | Amber highlight |
| `--danger` | `#B91C1C` | Errors and reverse actions |

Type is Inter (400–700) from Google Fonts, then `"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`.

WCAG 2.2 AA is the target: text contrast, a 3px focus ring, 44px minimum hit targets, a skip link, and `prefers-reduced-motion: reduce` turning off smooth scroll and shrinking animation. A formal accessibility audit has not been run.

The nav is visible without JavaScript. `public/js/worxevents.js` sets `data-collapsed` only on narrow viewports, and the stylesheet hides the nav only when that attribute is present.
