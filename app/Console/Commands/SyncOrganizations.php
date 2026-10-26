<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Organizations\HostnameTaken;
use App\Services\Organizations\OrganizationProvisioner;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class SyncOrganizations extends Command
{
    protected $signature = 'worxevents:organization:sync';

    protected $description = 'Apply config/organizations.php idempotently and print each organization host.';

    public function handle(OrganizationProvisioner $provisioner): int
    {
        $failed = false;

        foreach ((array) config('organizations', []) as $entry) {
            if (! is_array($entry) || ! isset($entry['slug'])) {
                continue;
            }

            $slug = (string) $entry['slug'];

            try {
                $result = $provisioner->apply(
                    $slug,
                    (string) ($entry['name'] ?? $slug),
                    isset($entry['owner_email']) ? (string) $entry['owner_email'] : null,
                    (string) ($entry['timezone'] ?? 'UTC'),
                    isset($entry['hostname']) ? (string) $entry['hostname'] : null,
                );
            } catch (HostnameTaken $exception) {
                $this->warn('organization='.$slug.' skipped='.$exception->getMessage());

                continue;
            } catch (InvalidArgumentException $exception) {
                $this->error('organization='.$slug.' error='.$exception->getMessage());
                $failed = true;

                continue;
            }

            $this->line(sprintf(
                'organization=%s created=%s owner=%s owner_link_sent=%s',
                $result['organization']->slug,
                $result['created'] === [] ? 'none' : implode(',', $result['created']),
                empty($entry['owner_email']) ? 'missing' : 'set',
                $result['link_sent'] ? 'yes' : 'no',
            ));
            $this->line('organization_host='.$result['hostname']);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
