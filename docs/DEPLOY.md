# Deployment

`scripts/release.sh` activates a release that was already built, or rolls back to the previous one. It does not install PHP dependencies, does not run migrations, and does not print the environment file.

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

GNU `mv -Tf` replaces the symlink. The first deploy needs `laravel/shared/.env` to exist already. Create that file on the server. Do not copy it from git.

## What a deploy deletes

- The rollback before the current one, so only two release trees remain. That is the inode control for `vendor`.
- Archives in `laravel/incoming`.
- Files under `shared/storage/framework/cache/data` and compiled views.
- All but the two newest files in `shared/backups` and `shared/storage/logs`.
- `node_modules` and `.git` if an upload contained them.

`tests/scripts/release_test.sh` deploys three trees, checks that only current and previous remain, checks that a secret baked into the upload is gone, checks that `public_html` has no `.env`, rolls back, and checks that a base path containing `public_html` is refused.

## Cron

After the site exists, one Site Tools cron, every minute, using PHP 8.2 or newer:

```text
php /home/<account>/www/app.worxevents.com/laravel/current/artisan schedule:run
```

The scheduler runs `queue:work database --stop-when-empty --tries=3 --max-time=20` with `withoutOverlapping`. The queue table is `background_jobs`. Until this cron exists, password resets and invitations are sent inside the web request on purpose.

## HTTPS, mail, backups

- Force HTTPS in Site Tools. The application also marks session cookies `Secure` when `APP_ENV=production`.
- Point `MAIL_*` at the host relay. Keep the password in `shared/.env`.
- Take a database dump into `laravel/shared/backups` before a migration. The script keeps the two newest files and will delete older ones on the next deploy.
- Migrations are a separate `php artisan migrate --force` from the current release, after a dump, and only when someone intends to change the schema. The release script does not run them.

## Inodes

Planning cap: about 600k. Two Laravel trees with `vendor` are the bulk. Do not leave old releases, old `vendor` directories, `node_modules`, compiled views, or unbounded logs on the account. This application does not need a Node build on the server.
