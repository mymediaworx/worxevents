# Roles and permissions

`app/Authorization/RoleCatalog.php` is the source of truth. The migration seeder writes it. `tests/Feature/Authorization/RoleGateTest.php` fails if the seeded rows drift.

Platform roles are `platform_members`. Organization roles are `organization_members`. A person may belong to several organizations. A platform role does not grant an organization role.

## Permissions

| Slug | Meaning |
|---|---|
| `platform.organizations.view` | View organizations |
| `platform.organizations.manage` | Manage organizations |
| `platform.fees.view` | View platform fee rules |
| `platform.fees.manage` | Create a new platform fee rule |
| `platform.audit.view` | View the platform audit log |
| `platform.settings.manage` | Manage platform settings |
| `organization.view` | View the organization |
| `organization.settings.update` | Update organization settings |
| `organization.members.view` | View members |
| `organization.members.manage` | Invite members, except as owner |
| `events.view` | View events |
| `events.manage` | Manage events |
| `tickets.sell` | Box office |
| `checkin.scan` | Check-in |
| `finance.view` | View finance |
| `finance.refunds.decide` | Request and submit a refund |
| `orders.view_own` | View the signed-in buyer's own orders |

## Matrix

Yes means the role has that permission.

| Permission | Super admin | Support admin | Owner | Admin | Event manager | Box office | Check-in | Finance | Buyer |
|---|---|---|---|---|---|---|---|---|---|
| `platform.organizations.view` | yes | yes | | | | | | | |
| `platform.organizations.manage` | yes | | | | | | | | |
| `platform.fees.view` | yes | yes | | | | | | | |
| `platform.fees.manage` | yes | | | | | | | | |
| `platform.audit.view` | yes | yes | | | | | | | |
| `platform.settings.manage` | yes | | | | | | | | |
| `organization.view` | | | yes | yes | yes | yes | yes | yes | |
| `organization.settings.update` | | | yes | yes | | | | | |
| `organization.members.view` | | | yes | yes | | | | | |
| `organization.members.manage` | | | yes | yes | | | | | |
| `events.view` | | | yes | yes | yes | yes | yes | yes | |
| `events.manage` | | | yes | yes | yes | | | | |
| `tickets.sell` | | | yes | yes | | yes | | | |
| `checkin.scan` | | | yes | yes | yes | yes | yes | | |
| `finance.view` | | | yes | yes | | | | yes | |
| `finance.refunds.decide` | | | yes | | | | | yes | |
| `orders.view_own` | | | | | | | | | yes |

Slugs: `platform_super_admin`, `platform_support_admin`, `org_owner`, `org_admin`, `event_manager`, `box_office`, `check_in_staff`, `finance`, `buyer`.

Gates are evaluated on the server for the organization resolved from the hostname. A buyer who is also an owner of a different organization does not carry that owner's permissions onto this host.
