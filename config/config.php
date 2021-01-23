<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'paymob_api_key' => env('PAYMOB_API_KEY'),
    'paymob_mood' => env('PAYMOB_MOOD', 'development'),
    'paymob_iframe_id' => env('PAYMOB_IFRAME_ID', ''),
    'paymob_fixed_fee' => env('PAYMOB_FIXED_FEE', 0),
    'paymob_percentage_fee' => env('PAYMOB_PERCENTAGE_FEE', 0),
    'paymob_live_integration_id' => env('PAYMOB_LIVE_INTEGRATION_ID', 1),
    'paymob_sandbox_integration_id' => env('PAYMOB_SANDBOX_INTEGRATION_ID')
];