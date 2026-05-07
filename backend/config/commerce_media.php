<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product Images Disk
    |--------------------------------------------------------------------------
    |
    | Public locally, S3-compatible later. Storefront URLs are generated from
    | the stored path, so keep paths relative to the chosen disk root.
    |
    */

    'product_images_disk' => env('PRODUCT_IMAGES_DISK', 'public'),
];
