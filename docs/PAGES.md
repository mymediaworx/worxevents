# Pages and design system

Routes are server-rendered. A shell page still checks the permission. Livewire is used for the team roster only.

| Route | Who | What Phase 1 shows |
|---|---|---|
| `GET /` on a platform host | Public | Marketing home |
| `GET /` on an organization host | Public | Organization name and public shell |
| `GET /design` | Public | Design tokens |
| `GET/POST /login`, `/register` | Guest | Tenant-bound sign-in and buyer registration |
| `GET/POST /forgot-password`, `/reset-password` | Guest | Expiring reset |
| `GET /email/verify` | Signed-in | Verification notice |
| `GET /invitations/{token}` | Public | Invitation. Accept requires auth and a verified email |
| `GET /dashboard` | Organization member, verified | Home for that organization |
| `GET /events` | `events.view` | Events shell |
| `GET /box-office` | `tickets.sell` | Box office shell |
| `GET /check-in` | `checkin.scan` | Check-in shell |
| `GET /tickets` | `orders.view_own` | Buyer orders shell |
| `GET /team`, `POST /team/invitations` | View, then manage | Roster and invitations |
| `GET /finance` | `finance.view` | Refund list |
| `POST /finance/refunds` | `finance.refunds.decide` | Request a refund |
| `POST /finance/refunds/{refund}/submit` | `finance.refunds.decide` | Submit to the gateway, which is disabled |
| `GET /platform` | Platform member | Organization list shell |
| `GET/POST /platform/fees` | View, then manage | Immutable platform fee rules |
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
