<?php

use Illuminate\Support\Facades\Schedule;

$maxTime = (int) config('worxevents.queue_max_time', 20);

Schedule::command(sprintf(
    'queue:work database --stop-when-empty --tries=3 --max-time=%d',
    $maxTime,
))->everyMinute()->withoutOverlapping();

Schedule::command('worxevents:reservations:release')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('worxevents:seats:release')
    ->everyMinute()
    ->withoutOverlapping();

// Keep shared storage lean on SiteGround (inode cap). Views/cache rebuild;
// file sessions older than two days are dropped. DB sessions are untouched.
Schedule::call(function () {
    $clearFiles = static function (string $dir, bool $recursive = false) use (&$clearFiles): void {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                if ($recursive) {
                    $clearFiles($path, true);
                    @rmdir($path);
                }

                continue;
            }
            if (is_file($path)) {
                @unlink($path);
            }
        }
    };

    $clearFiles(storage_path('framework/views'));
    $clearFiles(storage_path('framework/cache/data'), true);

    $sessions = storage_path('framework/sessions');
    if (is_dir($sessions)) {
        $cutoff = time() - 172800;
        foreach (scandir($sessions) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore') {
                continue;
            }
            $path = $sessions.DIRECTORY_SEPARATOR.$item;
            if (is_file($path) && filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }

    $logs = storage_path('logs');
    if (is_dir($logs)) {
        $cutoff = time() - 604800;
        foreach (scandir($logs) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitignore') {
                continue;
            }
            $path = $logs.DIRECTORY_SEPARATOR.$item;
            if (is_file($path) && filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }
})->daily()->name('worxevents-prune-runtime')->withoutOverlapping();
