# Update and rollback

## Ship a release

1. CI on `main` must pass (Pint, PHPStan, `composer audit`, and PHPUnit on MySQL 8.4 and MariaDB 11.8).
2. The Deploy job starts from that successful run. There is no manual approval gate.
3. The job builds `vendor` on the runner and runs `scripts/remote-activate.sh` over SSH. The document root is `$WORXEVENTS_BASE/public_html`. The default base is `~/www/app.worxevents.com`. Override it with the Actions variable `WORXEVENTS_BASE`. The base path must not contain `public_html`.
4. The script writes `laravel/shared/.env` outside the document root when it is missing, takes a database backup, then migrates.

Secrets used by the job are `SG_SSH_HOST`, `SG_SSH_USER`, `SG_SSH_PORT`, `SG_SSH_KEY`, `SG_SSH_PASSPHRASE`, and the optional `DB_*` and `APP_KEY` values. They are not in git. The script does not print them.

If the site directory does not exist yet, the job exits with `missing=document_root` and does not create a SiteGround site.

## Roll back the code

`scripts/release.sh` keeps `laravel/current` and `laravel/previous`. A rollback points `current` at the previous tree. It does not run composer and it does not migrate. Read [BACKUP.md](BACKUP.md) before rolling back a release that already migrated.

## After the first boot

The deploy script writes `DB_*` and non-empty `MAIL_*` values into `laravel/shared/.env` without printing them, dumps the database, migrates, then seeds the platform admin and the demo organizer. SMTP uses scheme `smtps` on port 465 once those mail values are present. HTTPS redirect and secure cookies are in the app; leave SiteGround HTTPS Enforce off. If the log says `manual=cron`, add the printed `cron_command` in SiteGround Site Tools → Devs → Cron Jobs, every minute. `config/organizations.php` stays empty. The demo hostname is an `organization_domains` row, not a committed config entry.
