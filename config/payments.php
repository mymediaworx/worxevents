<?php

declare(strict_types=1);

return [
    'default' => env('PAYMENTS_GATEWAY', 'stripe'),
    'fee_label' => 'Booking fee',
];
