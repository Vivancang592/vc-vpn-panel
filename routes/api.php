<?php

return [
    // API cấp liên kết Đăng ký cho App Client (V2Ray, Clash, Sing-Box...)
    'GET /sub'                         => ['Api\ClientController', 'subscribe'],

    // API Gateway giao tiếp Máy chủ Node VPS (Tích hợp unified endpoint cho Go/Bash)
    'POST /api/server/checkin'      => ['Api\ServerController', 'checkin'],
    'POST /api/server/sync'         => ['Api\ServerController', 'checkin'],
    'GET /api/server/users'         => ['Api\ServerController', 'users'],

    // API Webhook xử lý thanh toán tự động
    'POST /api/payment/webhook'     => ['Api\PaymentController', 'webhook'],

    // API Chatbot AI cho website
    'POST /api/chat/message'        => ['Api\ChatbotController', 'message'],
    'GET /api/chat/history'         => ['Api\ChatbotController', 'history'],
    'POST /api/chat/event'          => ['Api\ChatbotController', 'event'],
    'POST /api/chat/reset'          => ['Api\ChatbotController', 'reset'],

    // API Webhook Facebook Fanpage
    'GET /api/fanpage/webhook'      => ['Api\FanpageWebhookController', 'verify'],
    'POST /api/fanpage/webhook'     => ['Api\FanpageWebhookController', 'webhook'],
];