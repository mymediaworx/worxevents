# Onboarding

Phase 1 can create empty organizations, members, and a platform administrator. It cannot sell a ticket yet.

## Application

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set `DB_*` in `.env`. Leave `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` empty until a sandbox exists. Then:

```bash
php artisan migrate
```

Migrations seed roles, the platform settings (pagination 100, queue max 20 seconds, label "Booking fee"), and the launch fee rule.

## Platform administrator

On the host that serves the platform (`PLATFORM_DOMAINS`, default `app.worxevents.com`):

```bash
php artisan worxevents:platform-admin you@example.com
```

A new user gets a random password that is not printed and not emailed. The command sends a password-reset link to the first platform domain. The link expires in 60 minutes. Opening it also verifies the email. Running the command again does not rotate the password.

Public registration does not create platform administrators.

## Organizations

Add rows only in a reviewed local or server copy. Do not commit real customer emails if this repository is still public. Shape:

```php
return [
    [
        'slug' => 'harbor',
        'name' => 'Harbor Events',
        'owner_email' => 'owner@example.com',
        'timezone' => 'America/Chicago',
        'hostname' => 'harbor.worxevents.com',
    ],
];
```

`config/organizations.php` in git returns an empty list. Then:

```bash
php artisan worxevents:organization:sync
```

The command prints `organization_host=...`. It creates the organization in `onboarding`, a primary domain with `verified_at` set because the config is trusted, and an owner membership. A brand-new owner receives a reset link for that hostname and does not receive a password. A second sync does not rotate the password.

Slugs are 3–63 characters. Reserved slugs include `www`, `app`, `admin`, `api`, `mail`, `platform`, and `localhost`. A hostname already used by another organization is skipped. Platform hostnames are refused.

## Staff and buyers

Owners and admins invite staff from `/team`. The invitation cannot be for `org_owner`. The email contains a link that lasts 7 days. The recipient signs in with that email, verifies it, and accepts on the same host.

Buyers register on the organization host. That creates a `buyer` membership for that organization only.

The owner can also finish `/setup` after sign-in: legal name, support email, description, logo, accent color, USD currency, and time zone. Each step saves. Finish marks setup complete and, if the organization is still `onboarding`, sets it `active`. Currency other than USD is rejected.

## Mail

Until cron is installed, reset, invitation, and ticket mail is sent during the request. `MAIL_MAILER=log` writes it to the log locally. Production needs the host's SMTP settings in `shared/.env`. See [DEPLOY.md](DEPLOY.md).

Cron should run `php artisan schedule:run` every minute. That drains the database queue and releases expired ticket holds (`worxevents:reservations:release`). Holds last 15 minutes.
