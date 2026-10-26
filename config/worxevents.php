<?php

declare(strict_types=1);

$domains = array_values(array_filter(array_map(
    static fn (string $domain): string => strtolower(trim($domain)),
    explode(',', (string) env('PLATFORM_DOMAINS', 'app.worxevents.com')),
)));

return [
    'platform_domains' => $domains,
    'apex' => env('WORXEVENTS_APEX', 'worxevents.com'),
    'pagination_max' => 100,
    'queue_max_time' => (int) env('QUEUE_WORK_MAX_TIME', 20),
    'reservation_minutes' => 15,
];
