# Organizer manual

Work on the organization hostname, not the platform host. The owner and admin roles can change settings. Finance can see money and decide refunds. Box office can sell at the door. Check-in staff can scan and cannot undo a scan.

## Setup

1. Open Setup. Enter the legal name, support email, logo, USD, and time zone. Other currencies are rejected.
2. Open Payments. Sandbox Authorize.net credentials are checked before they are stored. The transaction key and signature key are encrypted and are not shown again. Live mode is rejected while `AUTHORIZE_NET_LIVE` is false.
3. Add a venue, then an event. An event stays a draft until it has a ticket type and you publish it. Drafts are not in the sitemap and return 404 to the public.

## Tickets

Ticket types have a price in cents, a quantity, per-order limits, a sales window, and visibility (`public`, `unlisted`, or `hidden`). A hold lasts 15 minutes. The release command runs from cron.

Free checkout completes immediately and records a $0 booking fee. Paid checkout shows the server total, including the booking fee, and issues tickets only after Authorize.net approves the charge. Card numbers are typed into Accept.js and are not posted to WorxEvents.

## Discounts, add-ons, seats, and series

On the event's extras page:

- Generate daily or weekly timed sessions. They stop at the event's end.
- Add a percent code (basis points, so 1000 is 10%) or a fixed amount in cents. You can set an expiry, a redemption cap, and a single ticket type.
- Add-ons have their own price and quantity. They are charged and are not part of the booking-fee base.
- Seats are a plan, sections, rows, and seats. Mark an accessible seat with the checkbox. Buyers see the word "accessible" in the label. A visual seat designer is not included.
- The waitlist stores one email per event. Offering a place does not sell a ticket.

A transfer voids the old QR token and emails a new one.

## Event day

Check-in uses the browser BarcodeDetector when it exists, or a typed code. A second scan of the same ticket is rejected. Undo requires `events.manage`.

Box office can record cash, a card taken at the door, or a comp. Those sales share inventory with the website. The card is a record. It is not sent to Authorize.net. The booking fee is not assessed (decision D10).

The attendee CSV is the first 100 rows and does not include ticket tokens.

## Mail

Buyers opt in with a checkbox. Announcements go only to those addresses, and each message has an unsubscribe link. A person who unsubscribes is left off the next send.
