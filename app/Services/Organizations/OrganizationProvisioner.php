<?php

declare(strict_types=1);

namespace App\Services\Organizations;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\OrganizationDomain;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WorkspacePasswordReset;
use App\Support\Hostname;
use App\Support\PasswordResetTokens;
use App\Support\Slugs;
use App\Tenancy\OrganizationManager;
use App\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class OrganizationProvisioner
{
    /**
     * @return array{organization: Organization, hostname: string, created: list<string>, link_sent: bool}
     */
    public function apply(string $slug, string $name, ?string $ownerEmail, string $timezone, ?string $hostname = null): array
    {
        return app(OrganizationManager::class)->bypass(
            fn (): array => $this->applyInside($slug, $name, $ownerEmail, $timezone, $hostname),
        );
    }

    /**
     * @return array{organization: Organization, hostname: string, created: list<string>, link_sent: bool}
     */
    private function applyInside(string $slug, string $name, ?string $ownerEmail, string $timezone, ?string $hostname): array
    {
        $slug = strtolower(trim($slug));
        $name = trim($name);
        $timezone = trim($timezone) === '' ? 'UTC' : trim($timezone);

        if (! Slugs::isWellFormed($slug) || Slugs::isReserved($slug)) {
            throw new InvalidArgumentException('Slug must be 3–63 lowercase letters, digits, or hyphens, and must not be reserved.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Organization name is required.');
        }

        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Timezone is not a known identifier.');
        }

        $apex = (string) config('worxevents.apex');
        $hostname = $hostname === null || trim($hostname) === ''
            ? $slug.'.'.$apex
            : Hostname::normalize($hostname);

        if ($hostname === '' || strlen($hostname) > 253 || ! str_contains($hostname, '.')) {
            throw new InvalidArgumentException('Hostname is not valid.');
        }

        if (Hostname::isPlatform($hostname)) {
            throw new InvalidArgumentException('That hostname is reserved for the platform.');
        }

        $existing = OrganizationDomain::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('hostname', $hostname)
            ->first();

        if ($existing instanceof OrganizationDomain) {
            $owner = Organization::query()->find($existing->organization_id);

            if (! $owner instanceof Organization || $owner->slug !== $slug) {
                throw new HostnameTaken('Hostname belongs to another organization.');
            }
        }

        $created = [];
        $linkSent = false;
        $organization = null;

        DB::transaction(function () use (&$organization, &$created, &$linkSent, $slug, $name, $ownerEmail, $timezone, $hostname): void {
            $organization = Organization::query()->where('slug', $slug)->first();

            if (! $organization instanceof Organization) {
                $organization = Organization::query()->create([
                    'name' => $name,
                    'slug' => $slug,
                    'status' => OrganizationStatus::Onboarding,
                    'timezone' => $timezone,
                ]);
                $created[] = 'organization';
            }

            $domain = OrganizationDomain::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('hostname', $hostname)
                ->first();

            if (! $domain instanceof OrganizationDomain) {
                OrganizationDomain::query()->create([
                    'organization_id' => $organization->id,
                    'hostname' => $hostname,
                    'type' => str_ends_with($hostname, '.'.config('worxevents.apex')) ? 'subdomain' : 'custom',
                    'verified_at' => now(),
                    'is_primary' => true,
                ]);
                $created[] = 'domain';
            }

            $email = $ownerEmail === null ? '' : strtolower(trim($ownerEmail));

            if ($email === '') {
                return;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Owner email is not valid.');
            }

            $user = User::query()->where('email', $email)->first();
            $freshUser = false;

            if (! $user instanceof User) {
                $user = User::query()->create([
                    'name' => $name.' owner',
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                ]);
                $freshUser = true;
                $created[] = 'user';
            }

            $role = Role::query()->where('slug', 'org_owner')->firstOrFail();
            $membership = OrganizationMember::query()
                ->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $membership instanceof OrganizationMember) {
                OrganizationMember::query()->create([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'status' => MembershipStatus::Active,
                ]);
                $created[] = 'membership';

                if ($freshUser) {
                    $token = PasswordResetTokens::create($user);
                    $user->notify(new WorkspacePasswordReset($token, $hostname));
                    $linkSent = true;
                }
            }
        });

        if (! $organization instanceof Organization) {
            throw new InvalidArgumentException('Organization was not saved.');
        }

        return [
            'organization' => $organization,
            'hostname' => $hostname,
            'created' => $created,
            'link_sent' => $linkSent,
        ];
    }
}
