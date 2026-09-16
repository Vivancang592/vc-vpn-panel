<?php

namespace App\Services;

class VpnService
{
    /**
     * Tạo UUID ngẫu nhiên chuẩn v4 cho tài khoản VPN
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Quy đổi dung lượng Bytes sang GB
     */
    public function bytesToGb(int $bytes): float
    {
        return round($bytes / (1024 * 1024 * 1024), 2);
    }

    /**
     * Quy đổi GB sang Bytes
     */
    public function gbToBytes(float $gb): int
    {
        return (int)($gb * 1024 * 1024 * 1024);
    }

    /**
     * Ráp chuỗi liên kết Proxy động dựa theo dữ liệu Node Inbound & Server
     */
    public function buildLink(array $inbound, string $uuid): ?string
    {
        $protocol    = strtolower($inbound['protocol'] ?? '');
        $ip          = $inbound['ip_address'] ?? '127.0.0.1';
        $port        = (int)($inbound['port'] ?? 443);
        $serverName  = $inbound['server_name'] ?? $inbound['name'] ?? 'VC-VPN';
        $network     = strtolower($inbound['network'] ?? 'tcp');
        $tls         = !empty($inbound['tls']) ? 'tls' : 'none';
        $sni         = $inbound['sni'] ?? '';
        $host        = $inbound['host'] ?? '';
        $path        = $inbound['path'] ?? '';
        $publicKey   = $inbound['public_key'] ?? '';
        $shortId     = $inbound['short_id'] ?? '';
        $serviceName = $inbound['service_name'] ?? '';
        $password    = $inbound['password'] ?? '';
        $tag         = $inbound['tag'] ?? '';

        // Tên hiển thị cuối link (#Tag hoặc #ServerName-Port)
        $displayName = !empty($tag) ? $tag : "{$serverName}-{$port}";

        switch ($protocol) {
            case 'vless':
                return $this->buildVless($uuid, $ip, $port, $displayName, $network, $tls, $sni, $host, $path, $publicKey, $shortId, $serviceName);
            case 'vmess':
                return $this->buildVmess($uuid, $ip, $port, $displayName, $network, $tls, $sni, $host, $path);
            case 'trojan':
                return $this->buildTrojan($uuid, $ip, $port, $displayName, $network, $tls, $sni, $host, $path);
            case 'shadowsocks':
            case 'ss':
                return $this->buildShadowsocks($password ?: $uuid, $ip, $port, $displayName);
            case 'hy2':
            case 'hysteria2':
                return $this->buildHysteria2($uuid, $ip, $port, $displayName, $sni ?: $ip);
            case 'tuic':
                return $this->buildTuic($uuid, $password ?: $uuid, $ip, $port, $displayName, $sni ?: $ip);
            default:
                return null;
        }
    }

    private function buildVless(
        string $uuid, string $ip, int $port, string $name, string $network, 
        string $tls, string $sni, string $host, string $path, 
        string $pbk, string $sid, string $serviceName
    ): string {
        $params = [
            'encryption' => 'none'
        ];

        // 1. Phân loại Reality vs TLS
        if (!empty($pbk)) {
            $params['security'] = 'reality';
            if ($network === 'tcp') {
                $params['flow'] = 'xtls-rprx-vision';
            }
            $params['sni'] = !empty($sni) ? $sni : $ip;
            $params['fp']  = 'chrome';
            $params['pbk'] = $pbk;
            if (!empty($sid)) {
                $params['sid'] = $sid;
            }
        } else {
            if ($tls !== 'none') {
                $params['security'] = 'tls';
                $params['sni']      = !empty($sni) ? $sni : ($host ?: $ip);
            }
        }

        // 2. Phân loại Mạng truyền tải (tcp, ws, grpc)
        $params['type'] = $network;

        if ($network === 'ws') {
            if (!empty($path)) $params['path'] = $path;
            if (!empty($host)) $params['host'] = $host;
            $params['allowInsecure'] = '1';
        } elseif ($network === 'grpc') {
            if (!empty($serviceName)) $params['serviceName'] = $serviceName;
        }

        $query = http_build_query($params);
        return "vless://{$uuid}@{$ip}:{$port}?" . $query . "#" . rawurlencode($name);
    }

    private function buildVmess(string $uuid, string $ip, int $port, string $name, string $network, string $tls, string $sni, string $host, string $path): string
    {
        $config = [
            'v'    => '2',
            'ps'   => $name,
            'add'  => $ip,
            'port' => (string)$port,
            'id'   => $uuid,
            'aid'  => '0',
            'scy'  => 'auto',
            'net'  => $network,
            'type' => 'none',
            'host' => $host,
            'path' => $path,
            'tls'  => $tls === 'tls' ? 'tls' : '',
            'sni'  => $sni
        ];
        return "vmess://" . base64_encode(json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function buildTrojan(string $uuid, string $ip, int $port, string $name, string $network, string $tls, string $sni, string $host, string $path): string
    {
        $params = [];
        if ($network !== 'tcp') $params['type'] = $network;
        if ($tls !== 'none') $params['security'] = $tls;
        if (!empty($sni)) $params['sni'] = $sni;
        if (!empty($host)) $params['host'] = $host;
        if (!empty($path)) $params['path'] = $path;

        $query = http_build_query($params);
        return "trojan://{$uuid}@{$ip}:{$port}" . ($query ? "?{$query}" : "") . "#" . rawurlencode($name);
    }

    private function buildShadowsocks(string $pass, string $ip, int $port, string $name): string
    {
        $userinfo = base64_encode("2022-blake3-aes-128-gcm:{$pass}");
        return "ss://{$userinfo}@{$ip}:{$port}#" . rawurlencode($name);
    }

    private function buildHysteria2(string $uuid, string $ip, int $port, string $name, string $sni): string
    {
        $params = [
            'sni'      => $sni,
            'insecure' => '1'
        ];
        $query = http_build_query($params);
        return "hysteria2://{$uuid}@{$ip}:{$port}?" . $query . "#" . rawurlencode($name);
    }

    private function buildTuic(string $uuid, string $pass, string $ip, int $port, string $name, string $sni): string
    {
        $params = [
            'congestion_control' => 'bbr',
            'sni'                => $sni,
            'alpn'               => 'h3',
            'insecure'           => '1'
        ];
        $query = http_build_query($params);
        return "tuic://{$uuid}:{$pass}@{$ip}:{$port}?" . $query . "#" . rawurlencode($name);
    }
}