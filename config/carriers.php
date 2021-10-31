<?php

return [
    'aramex' => [
        'USERNAME' => env('ARAMEX_USERNAME', null),
        'PASSWORD' => env('ARAMEX_PASSWORD', null),
        'ACCOUNT_NUMBER' => env('ARAMEX_ACCOUNT_NUMBER', null),
        'PIN' => env('ARAMEX_PIN', null),
        'ACCOUNT_ENTITY' => env('ARAMEX_ACCOUNT_ENTITY', null),
        'ACCOUNT_COUNTRY_CODE' => env('ARAMEX_ACCOUNT_COUNTRY_CODE', null),
        'VERSION' => env('VERSION', null),
        'SOURCE' => env('SOURCE',null)
    ],
    'stripe' => [
        'key' => env('STRIPE_ACCESS_KEY',null)
    ]
];