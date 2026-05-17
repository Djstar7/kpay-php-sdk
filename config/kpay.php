<?php

return [
    'base_url' => env('KPAY_BASE_URL', 'https://admin.kpay.site'),
    'api_key' => env('KPAY_API_KEY', ''),
    'secret_key' => env('KPAY_SECRET_KEY', ''),
    'gateway_secret' => env('KPAY_GATEWAY_SECRET'),

    // SEUL paramètre d'attente configurable : durée max (secondes) de
    // awaitFinalStatus avant d'abandonner le sondage (le webhook reste
    // le chemin principal de résolution).
    'max_duration' => (int) env('KPAY_MAX_DURATION', 300),
];
