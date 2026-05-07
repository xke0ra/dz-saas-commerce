<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Set this to the private IPs or CIDRs of the load balancer / reverse proxy
    | that is allowed to send X-Forwarded-* headers. Do not expose the backend
    | directly to the public internet when using broad values.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),
];
