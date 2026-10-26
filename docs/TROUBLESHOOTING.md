# Troubleshooting

## The organization address returns 403

The organization is suspended or terminated, or the signed-in person is not a member of that hostname. A platform administrator reinstates a suspension from Organizations. Membership is not shared across hostnames.

## The organization address returns 404

The hostname is not in `organization_domains` with `verified_at` set, or it is not the host the browser used. `worxevents:organization:sync` reads `config/organizations.php`. That file is empty in git. Do not commit customer hostnames if this repository is still public.

## Paid checkout says a card processor is required

Payments has no validated sandbox connection, or the saved environment is `live` while `AUTHORIZE_NET_LIVE` is false. Save sandbox credentials again. A declined charge releases the hold and does not issue tickets. If the processor times out after an approval, the order stays pending until the signed webhook settles it. Tickets are not issued twice.

## A discount or seat was consumed and the buyer was declined

A decline puts the discount redemption and the add-on quantity back and releases the seat. A charge that times out keeps the seat on the pending order until the webhook approves it or a later decline releases it.

## Check-in says the ticket is no longer valid

The token was transferred or reissued. The old code stays in the database so it cannot be reused. Scan the new message. A refunded ticket is reported as refunded. A second scan is reported as already checked in.

## Announcement sent to nobody

The buyer did not check the consent box, or they used the unsubscribe link. The send is capped at 100 consented addresses.

## Cron does not release holds

`schedule:run` must run every minute. It releases expired ticket reservations and seat holds that are not attached to a pending order. Mail and the queue also wait on that cron. Auth mail is sent during the request and does not wait for cron.

## Deploy did not start

The deploy workflow is on the phase 1 branch. It runs after CI succeeds on `main`, then waits for the GitHub `production` environment. Nothing in this repository merges itself. Until a person merges to `main`, the site is not deployed.

## Health is 503

`/platform/health` could not run `select 1`. Check `DB_*` in `laravel/shared/.env` on the server. The file is outside `public_html`. Do not copy it into git.
