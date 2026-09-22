<?php

return [
    'name' => getenv('APP_NAME') ?: 'VC VPN PANEL',
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Ho_Chi_Minh',
    'database_timezone' => getenv('DB_TIMEZONE') ?: '+07:00',
    'locale' => 'vi',

    /*
    |--------------------------------------------------------------------------
    | Cấu hình tiền tệ mặc định (Currency Settings)
    |--------------------------------------------------------------------------
    | Đơn vị tiền tệ chuẩn hóa duy nhất của hệ thống là Việt Nam Đồng (VND)
    |
    */
    'currency' => getenv('APP_CURRENCY') ?: 'VND',
    'currency_symbol' => getenv('APP_CURRENCY_SYMBOL') ?: 'đ',
    'currency_position' => getenv('APP_CURRENCY_POSITION') ?: 'right', // 'right' (100.000 đ) hoặc 'left' (đ 100.000)

    /*
    |--------------------------------------------------------------------------
    | Cấu hình Webhook MacroDroid / SePay
    |--------------------------------------------------------------------------
    */
    'macrodroid_secret' => getenv('MACRODROID_SECRET') ?: 'vc_vpn_macrodroid_secret_2027',
];