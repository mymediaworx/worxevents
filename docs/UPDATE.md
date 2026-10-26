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

Cron must call `php artisan schedule:run` every minute. HTTPS should be forced at the host. SMTP is not configured by the deploy script (`MAIL` stays `log` until someone sets it). `worxevents:platform-admin` creates the first platform user. `config/organizations.php` stays empty until hostnames are added on the server, not in this public repository.
