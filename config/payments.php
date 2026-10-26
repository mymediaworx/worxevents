<?php

declare(strict_types=1);

return [
    'default' => env('PAYMENTS_GATEWAY', 'stripe'),
    'fee_label' => 'Booking fee',

    // Live Authorize.net charges stay off until this flag is set on the server.
    'authorize_net_live' => filter_var(env('AUTHORIZE_NET_LIVE', false), FILTER_VALIDATE_BOOLEAN),
];
