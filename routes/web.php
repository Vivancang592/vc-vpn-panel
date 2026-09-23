<?php

return [
    // Trang chủ & Công khai (Public)
    'GET /'                      => ['HomeController', 'index'],
    'GET /download'              => ['HomeController', 'download'],
    'GET /client'                => ['HomeController', 'client'],
    'GET /faq'                   => ['HomeController', 'faq'],
    'GET /post-detail'           => ['HomeController', 'postDetail'],
    'GET /terms'                 => ['HomeController', 'terms'],
    'GET /privacy'               => ['HomeController', 'privacy'],
    'GET /refund'                => ['HomeController', 'refund'],

    // Xác thực tài khoản (Auth)
    'GET /login'                     => ['AuthController', 'showLogin'],
    'POST /login'                    => ['AuthController', 'login'],
    'GET /auth/google'               => ['AuthController', 'googleRedirect'],
    'GET /auth/google/callback'      => ['AuthController', 'googleCallback'],
    'GET /register'                  => ['AuthController', 'showRegister'],
    'POST /register'                 => ['AuthController', 'register'],
    'POST /register/send-otp'        => ['AuthController', 'sendRegisterOtp'],
    'GET /forgot-password'           => ['AuthController', 'showForgotPassword'],
    'POST /forgot-password'          => ['AuthController', 'forgotPassword'],
    'POST /forgot-password/send-otp' => ['AuthController', 'sendForgotPasswordOtp'],
    'GET /logout'                    => ['AuthController', 'logout'],
    
    // Khách hàng (User Dashboard)
    'GET /dashboard'             => ['UserController', 'dashboard'],
    'GET /user/guides'           => ['UserController', 'guides'],
    'GET /user/guides/detail'    => ['UserController', 'guideDetail'],
    'GET /user/articles/detail'  => ['UserController', 'articleDetail'],
    'GET /user/downloads'        => ['UserController', 'downloads'],
    
    // Hồ sơ cá nhân (Profile)
    'GET /profile'               => ['UserController', 'profile'],
    'POST /profile/update'       => ['UserController', 'updateProfile'],

    // Gói cước & Mua hàng (Plans & Checkout)
    'GET /user/plans'            => ['UserController', 'plans'],
    'GET /checkout'              => ['UserController', 'checkout'],
    'POST /checkout/coupon'       => ['UserController', 'previewCoupon'],
    'POST /checkout'             => ['UserController', 'buyPlan'],
    'GET /payment/checkout'      => ['UserController', 'paymentCheckout'],

    // Gói dịch vụ đã mua & Kết nối VPN (Subscriptions)
    'GET /subscriptions'         => ['UserController', 'subscriptions'],
    'GET /subscriptions/detail'  => ['UserController', 'subscriptionDetail'],
    'POST /subscription/renew'    => ['UserController', 'buyPlan'],

    // Đơn hàng (Orders)
    'GET /orders'                => ['UserController', 'orders'],
    'GET /orders/detail'         => ['UserController', 'orderDetail'],
    'GET /orders/status'         => ['UserController', 'orderStatus'],
    'POST /orders/cancel'        => ['UserController', 'cancelOrder'],

    // Lịch sử thanh toán & Nạp tiền (Payments)
    'GET /payments'              => ['UserController', 'payments'],
    'GET /payments/deposit'      => ['UserController', 'showDeposit'],
    'POST /payments/deposit'     => ['UserController', 'deposit'],
    'POST /payments/deposit/cancel' => ['UserController', 'cancelDeposit'],
    'GET /payments/status'       => ['UserController', 'paymentStatus'],

    // Ví tiền (Wallet)
    'GET /wallet'                => ['UserController', 'wallet'],
    'POST /wallet/deposit'       => ['UserController', 'walletDeposit'],

    // Tiếp thị liên kết (Referrals)
    'GET /referrals'             => ['UserController', 'referrals'],

    // Yêu cầu rút tiền (Withdrawals)
    'GET /withdrawals'           => ['UserController', 'withdrawals'],
    'GET /withdrawals/create'    => ['UserController', 'showCreateWithdrawal'],
    'POST /withdrawals/create'   => ['UserController', 'createWithdrawal'],

    // Hỗ trợ kỹ thuật (Tickets)
    'GET /tickets'               => ['UserController', 'tickets'],
    'GET /tickets/create'        => ['UserController', 'showCreateTicket'],
    'POST /tickets/create'       => ['UserController', 'createTicket'],
    'GET /tickets/detail'        => ['UserController', 'ticketDetail'],
    'POST /tickets/reply'        => ['UserController', 'replyTicket'],
    'POST /tickets/close'        => ['UserController', 'closeTicket'],

    // Thông báo (Notifications)
    'GET /notifications'              => ['UserController', 'notifications'],
    'POST /notifications/read-all'    => ['UserController', 'markAllNotificationsAsRead'],
    'POST /notifications/read'        => ['UserController', 'markNotificationAsRead'],
    'GET /notifications/read'         => ['UserController', 'markNotificationAsRead'],
    'POST /notifications/delete'      => ['UserController', 'deleteNotification'],
    'GET /notifications/delete'       => ['UserController', 'deleteNotification'],
    'POST /notifications/clear'       => ['UserController', 'clearAllNotifications'],

    // Quản trị viên (Admin Panel)
    'GET /admin'                          => ['Admin\DashboardController', 'index'],
    'GET /admin/notifications'            => ['Admin\DashboardController', 'notifications'],
    'POST /admin/notifications/read-all'  => ['Admin\DashboardController', 'markAllNotificationsAsRead'],
    'GET /admin/notifications/delete'     => ['Admin\DashboardController', 'deleteNotification'],
    'POST /admin/notifications/delete'    => ['Admin\DashboardController', 'deleteNotification'],
    'POST /admin/notifications/clear'     => ['Admin\DashboardController', 'clearAllNotifications'],
    
    // Quản lý người dùng (Users)
    'GET /admin/users'           => ['Admin\UserController', 'index'],
    'GET /admin/users/create'    => ['Admin\UserController', 'create'],
    'POST /admin/users/create'   => ['Admin\UserController', 'create'],
    'GET /admin/users/edit'      => ['Admin\UserController', 'edit'],
    'POST /admin/users/edit'     => ['Admin\UserController', 'edit'],
    'GET /admin/users/detail'    => ['Admin\UserController', 'detail'],
    'POST /admin/users/delete'   => ['Admin\UserController', 'delete'],

    // Quản lý nhóm máy chủ (Server Groups)
    'GET /admin/server-groups'          => ['Admin\ServerGroupController', 'index'],
    'GET /admin/server-groups/create'   => ['Admin\ServerGroupController', 'showCreate'],
    'POST /admin/server-groups/create'  => ['Admin\ServerGroupController', 'create'],
    'GET /admin/server-groups/edit'     => ['Admin\ServerGroupController', 'showEdit'],
    'POST /admin/server-groups/edit'    => ['Admin\ServerGroupController', 'edit'],
    'POST /admin/server-groups/delete'  => ['Admin\ServerGroupController', 'delete'],

    // Quản lý máy chủ (Servers)
    'GET /admin/servers'          => ['Admin\ServerController', 'index'],
    'GET /admin/servers/create'   => ['Admin\ServerController', 'showCreate'],
    'POST /admin/servers/create'  => ['Admin\ServerController', 'create'],
    'GET /admin/servers/edit'     => ['Admin\ServerController', 'showEdit'],
    'POST /admin/servers/edit'    => ['Admin\ServerController', 'edit'],
    'GET /admin/servers/detail'   => ['Admin\ServerController', 'detail'],
    'POST /admin/servers/delete'  => ['Admin\ServerController', 'delete'],
    'POST /admin/servers/sync'    => ['Admin\ServerController', 'sync'],

    // Quản lý nút kết nối (Nodes)
    'GET /admin/nodes'            => ['Admin\NodeController', 'index'],
    'GET /admin/nodes/detail'     => ['Admin\NodeController', 'detail'],
    'POST /admin/nodes/delete'    => ['Admin\NodeController', 'delete'],
    'POST /admin/nodes/bulk-delete' => ['Admin\NodeController', 'bulkDelete'],

    // Quản lý gói cước (Plans)
    'GET /admin/plans'            => ['Admin\PlanController', 'index'],
    'GET /admin/plans/create'     => ['Admin\PlanController', 'showCreate'],
    'POST /admin/plans/create'    => ['Admin\PlanController', 'create'],
    'GET /admin/plans/edit'       => ['Admin\PlanController', 'showEdit'],
    'POST /admin/plans/edit'      => ['Admin\PlanController', 'edit'],
    'POST /admin/plans/delete'     => ['Admin\PlanController', 'delete'],

    // Quản lý mã giảm giá (Coupons)
    'GET /admin/coupons'          => ['Admin\CouponController', 'index'],
    'GET /admin/coupons/create'   => ['Admin\CouponController', 'showCreate'],
    'POST /admin/coupons/create'  => ['Admin\CouponController', 'create'],
    'GET /admin/coupons/edit'     => ['Admin\CouponController', 'showEdit'],
    'POST /admin/coupons/edit'    => ['Admin\CouponController', 'edit'],
    'POST /admin/coupons/delete'   => ['Admin\CouponController', 'delete'],

    // Quản lý đơn hàng (Orders)
    'GET /admin/orders'                => ['Admin\OrderController', 'index'],
    'GET /admin/orders/create'         => ['Admin\OrderController', 'create'],
    'POST /admin/orders/create'        => ['Admin\OrderController', 'create'],
    'GET /admin/orders/detail'         => ['Admin\OrderController', 'detail'],
    'POST /admin/orders/update-status' => ['Admin\OrderController', 'updateStatus'],
    'POST /admin/orders/delete'        => ['Admin\OrderController', 'delete'],

    // Quản lý thanh toán (Payments)
    'GET /admin/payments'         => ['Admin\PaymentController', 'index'],
    'GET /admin/payments/detail'  => ['Admin\PaymentController', 'detail'],
    'POST /admin/payments/approve-deposit' => ['Admin\PaymentController', 'approveDeposit'],
    'POST /admin/payments/delete-cancelled-deposit' => ['Admin\PaymentController', 'deleteCancelledDeposit'],

    // Quản lý gói đăng ký (Subscriptions)
    'GET /admin/subscriptions'                => ['Admin\SubscriptionController', 'index'],
    'GET /admin/subscriptions/detail'         => ['Admin\SubscriptionController', 'detail'],
    'POST /admin/subscriptions/update-status' => ['Admin\SubscriptionController', 'updateStatus'],
    'POST /admin/subscriptions/renew'          => ['Admin\SubscriptionController', 'renew'],
    'POST /admin/subscriptions/reset-traffic'  => ['Admin\SubscriptionController', 'resetTraffic'],
    'POST /admin/subscriptions/reset-token'    => ['Admin\SubscriptionController', 'resetToken'],
    'POST /admin/subscriptions/delete'         => ['Admin\SubscriptionController', 'delete'],

    // Quản lý hoa hồng & giới thiệu (Referrals)
    'GET /admin/referrals'        => ['Admin\ReferralController', 'index'],

    // Quản lý yêu cầu rút tiền (Withdrawals)
    'GET /admin/withdrawals'        => ['Admin\WithdrawalController', 'index'],
    'GET /admin/withdrawals/detail' => ['Admin\WithdrawalController', 'detail'],
    'POST /admin/withdrawals/detail' => ['Admin\WithdrawalController', 'detail'],

    // Quản lý bài viết & tin tức (Posts)
    'GET /admin/posts'            => ['Admin\PostController', 'index'],
    'GET /admin/posts/create'     => ['Admin\PostController', 'showCreate'],
    'POST /admin/posts/create'    => ['Admin\PostController', 'create'],
    'GET /admin/posts/edit'       => ['Admin\PostController', 'showEdit'],
    'POST /admin/posts/edit'      => ['Admin\PostController', 'edit'],
    'GET /admin/posts/detail'     => ['Admin\PostController', 'detail'],
    'POST /admin/posts/delete'    => ['Admin\PostController', 'delete'],

    // Quản lý hỗ trợ (Tickets)
    'GET /admin/tickets'          => ['Admin\TicketController', 'index'],
    'GET /admin/tickets/detail'   => ['Admin\TicketController', 'detail'],
    'POST /admin/tickets/detail'  => ['Admin\TicketController', 'detail'],
    'POST /admin/tickets/delete'  => ['Admin\TicketController', 'delete'],

    // Quản lý chi phí (Expenses)
    'GET /admin/expenses'         => ['Admin\ExpenseController', 'index'],
    'GET /admin/expenses/create'  => ['Admin\ExpenseController', 'showCreate'],
    'POST /admin/expenses/create' => ['Admin\ExpenseController', 'create'],
    'GET /admin/expenses/edit'    => ['Admin\ExpenseController', 'showEdit'],
    'POST /admin/expenses/edit'   => ['Admin\ExpenseController', 'edit'],
    'POST /admin/expenses/delete'  => ['Admin\ExpenseController', 'delete'],

    // Cài đặt hệ thống (Settings)
    'GET /admin/settings'         => ['Admin\SettingController', 'index'],
    'POST /admin/settings/save'   => ['Admin\SettingController', 'save'],

    // Nhật ký hệ thống (Logs)
    'GET /admin/logs'                   => ['Admin\LogController', 'index'],
    'GET /admin/logs/system'            => ['Admin\LogController', 'system'],
    'GET /admin/logs/access'            => ['Admin\LogController', 'access'],
    'GET /admin/logs/email'             => ['Admin\LogController', 'email'],
    'GET /admin/logs/chatbot'           => ['Admin\LogController', 'chatbot'],
    'GET /admin/logs/macrodroid'        => ['Admin\LogController', 'macrodroid'],
    'POST /admin/logs/delete'           => ['Admin\LogController', 'delete'],
    'POST /admin/logs/macrodroid/clear' => ['Admin\LogController', 'clearMacrodroid'],

    // Tự động quét gói cước (Cron Job)
    'GET /api/cron/check-subscriptions' => ['CronController', 'checkSubscriptions'],
];