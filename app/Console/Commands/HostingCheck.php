<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HostingCheck extends Command
{
    protected $signature = 'worxevents:hosting-check';

    protected $description = 'Print non-secret hosting facts for the SiteGround verification checklist.';

    public function handle(): int
    {
        $this->line('php_version='.PHP_VERSION);
        $this->line('memory_limit='.(string) ini_get('memory_limit'));
        $this->line('max_execution_time='.(string) ini_get('max_execution_time'));

        foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip'] as $extension) {
            $this->line('extension='.$extension.'='.(extension_loaded($extension) ? 'yes' : 'no'));
        }

        try {
            $row = DB::selectOne('select version() as version');
            $version = is_object($row) && isset($row->version) && is_string($row->version) ? $row->version : 'unavailable';
            $this->line('db_version='.$version);
        } catch (Throwable) {
            $this->line('db_version=unavailable');
        }

        $this->line('queue_connection='.(string) config('queue.default'));
        $this->line('queue_table='.(string) config('queue.connections.database.table'));
        $this->line('queue_work_max_time='.(string) config('worxevents.queue_max_time'));

        return self::SUCCESS;
    }
}
