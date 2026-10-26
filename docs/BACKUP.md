# Backup and restore

The deploy script keeps two compressed database dumps in `laravel/shared/backups/` and two release trees (`current` and `previous`). Older release directories, incoming archives, and extra dumps are deleted because SiteGround limits inodes.

## What is backed up

`scripts/remote-activate.sh` runs `mysqldump --single-transaction --no-tablespaces` through a mode-600 defaults file, then `gzip`. It checks the archive with `gzip -t` before `php artisan migrate --force`. If `mysqldump` or `gzip` is missing, the script stops and does not migrate.

The dump is a database backup. It does not include `laravel/shared/.env` or uploaded files under `laravel/shared/storage`. Copy those separately if you need them. Do not put the copy in the git repository.

## Restore a database

On the server, from a shell that already has the database password in the environment and not on the command line:

```bash
gzip -dc laravel/shared/backups/<file>.sql.gz | mysql "$DB_DATABASE"
```

Use the MySQL client that matches the host. Confirm the dump before you replace a live database. The application should be pointed at `previous` or taken out of service first so new orders are not written into a database you are about to overwrite.

## Restore the code

See [UPDATE.md](UPDATE.md). `scripts/release.sh` with `ACTION=rollback` points `current` at `previous`. It does not restore the database. If a migration has already run, the previous code may not match the new schema. Restore the dump taken before that migration, then roll the code back.
