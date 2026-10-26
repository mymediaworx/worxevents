# Deployment

Pushes to `main` run CI. When that CI succeeds, `.github/workflows/deploy.yml` starts the **Deploy** job immediately. There is no manual approval gate. The job still fails if CI did not pass.

The site directory defaults to `~/www/app.worxevents.com`. The document root is always `$WORXEVENTS_BASE/public_html`, so the default document root is `~/www/app.worxevents.com/public_html`. Set the Actions variable `WORXEVENTS_BASE` to the directory that contains `public_html` if that path is different. Do not point it at `public_html` itself.

SSH secrets are `SG_SSH_HOST`, `SG_SSH_USER`, `SG_SSH_PORT`, `SG_SSH_KEY`, and `SG_SSH_PASSPHRASE`. Database secrets are `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. A verified `mysqldump` is required before `migrate`. The passphrase-protected key is unlocked in the runner and is not printed.

The job builds `vendor` on the runner, uploads one archive, and runs `scripts/remote-activate.sh` on the host. That script writes the database secrets into `laravel/shared/.env` (replacing them when a new non-empty value is provided) and does not print them. It generates `APP_KEY` on the server only when that file does not already have one. `MAIL_MAILER` stays `log` until SMTP is configured. `MAIL_LOG_CHANNEL=mail` records those messages in the shared log even when `LOG_LEVEL=error`. After a database login succeeds, the script dumps the database, runs `migrate --force`, then `worxevents:platform-admin michael@brandworx.ai` and `worxevents:demo`. None of those secret values belong in git. If the `app.worxevents.com` directory does not already exist, the job stops with `missing=document_root` and does not invent a SiteGround site.

Stripe secrets are not read and no Stripe call is made.

`scripts/release.sh` activates a release that was already built, or rolls back to the previous one. It does not install PHP dependencies and does not print the environment file. Migrations run from `remote-activate.sh` only after a database login succeeds.

Default base: `$HOME/www/app.worxevents.com`. Override with `WORXEVENTS_BASE`. The base path must be absolute and must not contain `public_html`. The script creates `public_html` under that base.

## Layout

```text
www/app.worxevents.com/
  public_html/index.php          requires ../laravel/current/public/index.php
  public_html/.htaccess          copy of the release's locked htaccess
  laravel/releases/<id>/         one built tree, including vendor
  laravel/current                symlink to the active release
  laravel/previous               symlink to the rollback release
  laravel/shared/.env            secrets, outside public_html
  laravel/shared/storage/        shared cache, sessions, views, logs
  laravel/shared/backups/        at most two dump files
  laravel/incoming/              upload slot; archives are deleted after deploy
```

Each release's `.env` is a symlink to `laravel/shared/.env`. Its `storage` directory is a symlink to the shared storage. A `.env` that arrived inside the upload is deleted before those links are made.

## Commands

```bash
# On the build machine, after composer install --no-dev --prefer-dist
export WORXEVENTS_BASE="$HOME/www/app.worxevents.com"
export ACTION=deploy
export RELEASE=20261026-1
export SOURCE=/path/to/built/tree
bash scripts/release.sh

export ACTION=rollback
bash scripts/release.sh
```

`RELEASE` is a short file-safe name. Deploy refuses to reuse an id. Rollback swaps `current` and `previous` and rewrites the front controller and `.htaccess`. It does not delete the tree you rolled off, and it does not run `migrate:rollback`.

GNU `mv -Tf` replaces the symlink. `remote-activate.sh` creates `laravel/shared/.env` on the server. Do not copy that file from git.

## What a deploy deletes

- The rollback before the current one, so only two release trees remain. That is the inode control for `vendor`.
- Archives in `laravel/incoming`.
- Files under `shared/storage/framework/cache/data` and compiled views.
- All but the two newest files in `shared/backups` and `shared/storage/logs`.
- `node_modules` and `.git` if an upload contained them.

`tests/scripts/release_test.sh` deploys three trees, checks that only current and previous remain, checks that a secret baked into the upload is gone, checks that `public_html` has no `.env`, rolls back, and checks that a base path containing `public_html` is refused.

## Cron

The activate script tries to install this crontab entry. If `crontab` is rejected, the log prints `manual=cron` and the exact line. Add that line in SiteGround Site Tools → Devs → Cron Jobs, every minute:

```text
* * * * * /usr/local/php82/bin/php /home/<account>/www/app.worxevents.com/laravel/current/artisan schedule:run >> /dev/null 2>&1
```

Use the `cron_command` value from the deploy log. The PHP path and home directory come from the server.

The scheduler runs `queue:work database --stop-when-empty --tries=3 --max-time=20` with `withoutOverlapping`. The queue table is `background_jobs`. Until this cron exists, password resets and invitations are sent inside the web request on purpose.

## HTTPS, mail, backups

- Leave SiteGround HTTPS Enforce off. Production redirects HTTP to HTTPS in the application and sets session cookies `Secure`.
- Leave `MAIL_MAILER=log` until SMTP is configured. The reset message is written to `laravel/shared/storage/logs/laravel.log`. Do not copy that file into the deploy log.
- The activate script dumps the database into `laravel/shared/backups` before `migrate --force`. The release script keeps the two newest files.
- `worxevents:platform-admin` creates `michael@brandworx.ai` when that user is new and sends one reset link to the log. A second run does not send another link. Forgot password on `https://app.worxevents.com` writes a new link to the same log. The link expires in 60 minutes. The new password needs 12 characters, mixed case, and a number.

## Inodes

Planning cap: about 600k. Two Laravel trees with `vendor` are the bulk. Do not leave old releases, old `vendor` directories, `node_modules`, compiled views, or unbounded logs on the account. This application does not need a Node build on the server.
