<?php

return [
    'name' => getenv('APP_NAME') ?: 'VC VPN PANEL',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'vi',

    /*
    |--------------------------------------------------------------------------
    | Cấu hình tiền tệ mặc định (Currency Settings)
    |--------------------------------------------------------------------------
    | Chuyển đổi đơn vị tiền tệ mặc định sang Nhân dân tệ (CNY)
    |
    */
    'currency' => getenv('APP_CURRENCY') ?: 'CNY',
    'currency_symbol' => getenv('APP_CURRENCY_SYMBOL') ?: '¥',
    'currency_position' => getenv('APP_CURRENCY_POSITION') ?: 'left', // 'left' (¥100) hoặc 'right' (100¥)

    /*
    |--------------------------------------------------------------------------
    | Cấu hình Webhook MacroDroid
    |--------------------------------------------------------------------------
    */
    'macrodroid_secret' => getenv('MACRODROID_SECRET') ?: 'vc_vpn_macrodroid_secret_2027',
];