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
