<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\EmailLog;

class MailService
{
    private array $settings = [];

    public function __construct()
    {
        if (class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $this->settings = $settingModel->getAllAsKeyValue();
        }
    }

    /**
     * Nạp và biên dịch Template HTML Email
     */
    public function renderTemplate(string $templatePath, array $data = []): string
    {
        $data['siteTitle'] = $data['siteTitle'] ?? ($this->settings['site_title'] ?? 'VC VPN 2027');
        $data['siteSubtitle'] = $data['siteSubtitle'] ?? ($this->settings['site_subtitle'] ?? $this->settings['site_description'] ?? 'An Toàn - Bảo Mật - Uy Tín');
        
        extract($data);
        $fullPath = BASE_PATH . '/resources/views/emails/' . str_replace('.', '/', $templatePath) . '.php';
        
        if (!file_exists($fullPath)) {
            return '';
        }
        
        ob_start();
        require $fullPath;
        return ob_get_clean() ?: '';
    }

    /**
     * Gửi Email qua SMTP Socket và ghi Log
     */
    public function send(string $to, string $subject, string $template, array $data = []): bool
    {
        $body = $this->renderTemplate($template, $data);
        if (empty($body)) {
            $this->logEmail($to, $subject, 'Template email không tồn tại.', 'failed', 'File template không tìm thấy.');
            return false;
        }

        $host = $this->settings['smtp_host'] ?? '';
        $port = (int)($this->settings['smtp_port'] ?? 465);
        $encryption = strtolower($this->settings['smtp_encryption'] ?? 'ssl');
        $username = $this->settings['smtp_username'] ?? '';
        $password = $this->settings['smtp_password'] ?? '';
        $fromAddress = !empty($this->settings['mail_from_address']) ? $this->settings['mail_from_address'] : $username;
        $fromName = !empty($this->settings['mail_from_name']) ? $this->settings['mail_from_name'] : ($this->settings['site_title'] ?? 'VC VPN');

        if (empty($host) || empty($username) || empty($password)) {
            $this->logEmail($to, $subject, $body, 'failed', 'Chưa cấu hình đầy đủ thông số máy chủ SMTP trong Quản Trị.');
            return false;
        }

        try {
            $success = $this->sendSmtpSocket($host, $port, $encryption, $username, $password, $fromAddress, $fromName, $to, $subject, $body);
            if ($success) {
                $this->logEmail($to, $subject, $body, 'sent');
                return true;
            }
            $this->logEmail($to, $subject, $body, 'failed', 'Máy chủ SMTP phản hồi lỗi xác thực hoặc từ chối kết nối.');
            return false;
        } catch (\Throwable $e) {
            $this->logEmail($to, $subject, $body, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Xử lý kết nối Socket SMTP thuần (Không cần thư viện ngoài)
     */
    private function sendSmtpSocket(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $fromAddress,
        string $fromName,
        string $to,
        string $subject,
        string $body
    ): bool {
        $prefix = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);

        if (!$socket) {
            return false;
        }

        $read = function() use ($socket) {
            $response = '';
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                if (substr($str, 3, 1) == ' ') break;
            }
            return $response;
        };

        $write = function($cmd) use ($socket) {
            fputs($socket, $cmd . "\r\n");
        };

        $read();
        $write("EHLO " . gethostname());
        $read();

        if ($encryption === 'tls') {
            $write("STARTTLS");
            $read();
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            $write("EHLO " . gethostname());
            $read();
        }

        $write("AUTH LOGIN");
        $read();

        $write(base64_encode($username));
        $read();

        $write(base64_encode($password));
        $authRes = $read();

        if (!str_starts_with(trim($authRes), '235')) {
            fclose($socket);
            return false;
        }

        $write("MAIL FROM: <{$fromAddress}>");
        $read();

        $write("RCPT TO: <{$to}>");
        $read();

        $write("DATA");
        $read();

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$encodedFromName} <{$fromAddress}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: {$encodedSubject}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $write($headers . "\r\n" . $body . "\r\n.");
        $dataRes = $read();

        $write("QUIT");
        fclose($socket);

        return str_starts_with(trim($dataRes), '250');
    }

    /**
     * Ghi nhật ký vào vc_email_logs
     */
    private function logEmail(string $recipient, string $subject, string $body, string $status, ?string $errorMessage = null): void
    {
        if (class_exists('App\Models\EmailLog')) {
            $emailLog = new EmailLog();
            $emailLog->create([
                'recipient'     => $recipient,
                'subject'       => $subject,
                'body'          => $body,
                'status'        => $status,
                'error_message' => $errorMessage,
                'created_at'    => date('Y-m-d H:i:s')
            ]);
        }
    }
}