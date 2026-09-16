<?php

namespace App\Models;

class SubscriptionAccessLog extends BaseModel
{
    protected string $table = 'vc_subscription_access_logs';

    public function record(
        int $subscriptionId,
        string $ipAddress,
        string $appName,
        string $osName,
        string $requestType,
        string $userAgent
    ): bool {
        return $this->create([
            'subscription_id' => $subscriptionId,
            'ip_address' => substr($ipAddress, 0, 45),
            'app_name' => substr($appName, 0, 100),
            'os_name' => substr($osName, 0, 50),
            'request_type' => in_array($requestType, ['vpn_app', 'browser', 'unknown'], true) ? $requestType : 'unknown',
            'user_agent' => substr($userAgent, 0, 1000),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}