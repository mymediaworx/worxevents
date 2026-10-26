# SiteGround verification checklist

Status: **not verified for WorxEvents**. No SSH, Site Tools, or DNS access to this domain was available while Phase 1 was built. Do not treat a sibling site's measurements as this site's measurements.

`php artisan worxevents:hosting-check` prints PHP version, `memory_limit`, `max_execution_time`, extension presence, `select version()` or `db_version=unavailable`, and the queue settings. It does not print a database password or a DSN. Run it on the host after access exists and paste the non-secret lines into the table below.

## What a sibling GoGeek account showed

These numbers are from ReserveWorx on `app.reserveworx.com` (SSH on 2026-10-23 and deploy run 40080763617 on 2026-10-24). They are a hypothesis for a similar plan, not a reading of WorxEvents.

| Check | Sibling result | WorxEvents |
|---|---|---|
| Plan | GoGeek, reported for that account | Unverified |
| PHP | CLI and the deploy binary 8.2.35. CGI trees exist for 7.4, 8.2, 8.3, 8.4, 8.5, 8.6. CGI binaries reject `php -r` | Unverified. This app requires 8.2+ |
| `memory_limit` | Artisan process 768M. One CGI probe reported 128M | Unverified |
| `max_execution_time` | Artisan 0. Web SAPI not read | Unverified |
| Database | `db_version=8.4.11-11`, InnoDB, `SELECT … FOR UPDATE` ok | Unverified. Local development used MySQL 8.0.46 |
| Cron | Not read from the host crontab. The app expects `schedule:run` every minute | Unverified |
| SSH port | Stated as 18765 for that account | Unverified. Do not commit the key |
| Composer on the server | Present, and deploys still build vendor elsewhere | This app also builds vendor before upload |
| Long-running workers | Assumed unavailable | This app does not need them |
| Wildcard DNS and wildcard SSL | Unverified there too | Unverified |

## Public behavior, not a measurement

SiteGround's edge returns HTTP 202 to many `curl` clients (bot check). A 202 does not mean Laravel answered. Check from a browser, or from SSH with `php artisan`, after the site exists.

Published plan limits (inode cap, CPU, process count) still have to be read from this account's Site Tools. The 600k inode figure is the planning cap this release script is written for.

## Checklist to run when access exists

- [ ] Plan name for the WorxEvents account
- [ ] PHP version selected for the site's web SAPI, and `worxevents:hosting-check` output
- [ ] `memory_limit` and `max_execution_time` for a web request, not only CLI
- [ ] Extensions: bcmath, ctype, curl, dom, fileinfo, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip
- [ ] Database version, engine InnoDB, and a transaction rollback
- [ ] Database user limited to the application schema. Password stored only in `laravel/shared/.env`
- [ ] Cron: `php artisan schedule:run` every minute, PHP 8.2+ binary
- [ ] HTTPS certificate installed, and HTTPS forced in Site Tools
- [ ] Document root is `public_html`, whose `index.php` boots `../laravel/current`
- [ ] `laravel/shared/.env` is not under `public_html`
- [ ] Subdomain or wildcard for `{slug}.worxevents.com`, plus the platform host `app.worxevents.com`
- [ ] Mail: SPF, DKIM, and a mailbox or SMTP relay. Laravel `MAIL_*` stays in `shared/.env`
- [ ] Backups: host backup plus the two gzip files the release script keeps
- [ ] Outbound HTTPS to `api.stripe.com` from the host, once Stripe is in scope
- [ ] Confirm a browser request does not receive the 202 bot interstitial

There is no GitHub Actions deploy workflow. Adding one requires hosting secrets stored in GitHub, not in this repository.
