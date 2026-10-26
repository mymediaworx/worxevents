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
| `GET /box-office` | `tickets.sell` | Box office shell |
| `GET /check-in` | `checkin.scan` | Check-in shell |
| `GET /tickets` | `orders.view_own` | The signed-in buyer's registrations on this organization |
| `GET /team`, `POST /team/invitations` | View, then manage | Roster and invitations |
| `GET /finance` | `finance.view` | Refund list |
| `POST /finance/refunds` | `finance.refunds.decide` | Request a refund |
| `POST /finance/refunds/{refund}/submit` | `finance.refunds.decide` | Submit to the gateway, which is disabled |
| `GET /platform` | Platform member | Organization list shell |
| `GET/POST /platform/fees` | View, then manage | Immutable platform fee rules |
| `GET /setup` | `organization.settings.update` | Organization details, logo, currency, and time zone |
| `GET /venues` | `events.view` | Venues. Writing requires `events.manage` |
| `GET /reports` | `finance.view` | Counts of published events, registrations, tickets, and fees |
| `GET /browse`, `/categories/{slug}` | Public | Search and filter published events. Drafts are omitted |
| `GET /e/{slug}` | Public | Event page, free registration, and a short inventory hold. Drafts are 404 |
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

Mobile-first. The visual target is a quiet, original layout: warm paper, near-black type, one accent. It is not an Apple theme and it does not copy another product's marks.

Tokens in `public/css/worxevents.css`:

| Token | Value | Use |
|---|---|---|
| `--paper` | `#f6f5f2` | Page background |
| `--surface` | `#ffffff` | Cards and header |
| `--ink` | `#1a1a18` | Text |
| `--muted` | `#5c5a55` | Secondary text |
| `--line` | `#e4e1da` | Borders |
| `--accent` | `#0c5c4e` | Links, focus, primary actions |
| `--danger` | `#8e2f2f` | Errors |

The font stack is `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`. No webfont is downloaded.

WCAG 2.2 AA is the target: text contrast, a 3px focus ring, 44px minimum hit targets, a skip link, and `prefers-reduced-motion: reduce` turning off smooth scroll and shrinking animation. A formal accessibility audit has not been run.

The nav is visible without JavaScript. `public/js/worxevents.js` sets `data-collapsed` only on narrow viewports, and the stylesheet hides the nav only when that attribute is present.
