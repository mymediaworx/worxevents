# WorxEvents

WorxEvents is a multi-tenant event ticketing and registration service. Phase 1 is the foundation: organizations, authentication, roles, the booking-fee engine, and the payment boundary. Checkout, events, and live charges are later phases.

This repository is public until it is made private. Do not commit `.env` files, tokens, webhook secrets, upgrade URLs, or customer data.

## Stack

Laravel 12, PHP 8.2 or newer, MySQL or MariaDB, server-rendered Blade and Livewire, and a small vanilla script for the mobile nav. Node, Redis, Docker, and long-running workers are not required to run the application. Styles and scripts are static files in `public/`.

Patterns follow [ReserveWorx](https://github.com/mymediaworx/reserveworx), which was readable while this phase was built. They were adapted, not copied in as a package. DistroSKU was not consulted.

## Docs

- [Architecture and Phase 1 plan](docs/ARCHITECTURE.md)
- [Decisions that need approval](docs/DECISIONS.md)
- [Role and permission matrix](docs/RBAC.md)
- [Booking fees](docs/FEES.md)
- [Stripe and Authorize.net](docs/PAYMENTS.md)
- [Pages and design system](docs/PAGES.md)
- [SiteGround checklist](docs/HOSTING.md)
- [Deploy, cron, and inode pruning](docs/DEPLOY.md)
- [Security checklist](docs/SECURITY.md)
- [Onboarding](docs/ONBOARDING.md)
- [Known limitations](docs/LIMITATIONS.md)
- [Backlog, phases 2–7](docs/BACKLOG.md)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database, set `DB_*` in the uncommitted `.env`, then:

```bash
php artisan migrate
php artisan worxevents:platform-admin you@example.com
php artisan worxevents:organization:sync
```

`config/organizations.php` ships empty. The platform-admin command emails a reset link and does not print a password.

## Tests

PHPUnit expects MySQL database `worxevents_test` with user `worxevents`. `phpunit.xml` sets that connection and does not read production secrets. A missing `.env` is copied from `.env.example` by `tests/bootstrap.php` and stays gitignored.

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
bash tests/scripts/release_test.sh
```

GitHub Actions runs Pint, PHPStan, `composer audit`, the release-script test, and PHPUnit on MySQL 8.4 and MariaDB 11.8. There is no deploy workflow until hosting credentials exist outside this repository.
