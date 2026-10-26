<?php

declare(strict_types=1);

$root = dirname(__DIR__);

// Feature tests boot Laravel, which reads .env. The file is never committed.
// Copy the example when it is missing so PHPUnit does not warn on the read.
if (! is_file($root.'/.env')) {
    copy($root.'/.env.example', $root.'/.env');
}

require $root.'/vendor/autoload.php';
