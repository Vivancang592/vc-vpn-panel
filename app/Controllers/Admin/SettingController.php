<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Setting;
use App\Models\VpnPlan;

class SettingController extends BaseController
{
    private Setting $settingModel;
    private VpnPlan $planModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->settingModel = new Setting();
        $this->planModel = new VpnPlan();
    }

    public function index(): void
    {
        $settings = $this->settingModel->getAllAsKeyValue();
        $plans    = $this->planModel->getAllActive();

        // Gán giá trị mặc định đơn vị tiền tệ CNY nếu chưa có trong cấu hình DB
        $settings['currency']        = $settings['currency'] ?? 'CNY';
        $settings['currency_symbol'] = $settings['currency_symbol'] ?? '¥';

        $this->render('admin.settings.index', [
            'activeMenu' => 'settings',
            'settings'   => $settings,
            'plans'      => $plans
        ]);
    }

    public function save(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settingsData = $_POST['settings'] ?? [];

            try {
                $uploadedQrImages = [
                    'wechat_qr_image' => $this->uploadPaymentQrImage('wechat_qr_upload', 'wechat'),
                    'alipay_qr_image' => $this->uploadPaymentQrImage('alipay_qr_upload', 'alipay')
                ];

                foreach ($settingsData as $key => $value) {
                    $this->settingModel->setByKey($key, is_string($value) ? trim($value) : $value);
                }

                foreach ($uploadedQrImages as $settingKey => $imagePath) {
                    if ($imagePath !== null) {
                        $this->settingModel->setByKey($settingKey, $imagePath);
                    }
                }

                $_SESSION['flash_message'] = 'Cập nhật cấu hình hệ thống thành công!';
                $_SESSION['flash_type']    = 'success';
            } catch (\RuntimeException $exception) {
                $_SESSION['flash_message'] = $exception->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }

        $this->redirect('/admin/settings');
    }

    private function uploadPaymentQrImage(string $field, string $gateway): ?string
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        if ($file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 5 * 1024 * 1024) {
            throw new \RuntimeException('Ảnh QR ' . $gateway . ' không hợp lệ hoặc vượt quá 5 MB.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        if (!isset($extensions[$mimeType])) {
            throw new \RuntimeException('Ảnh QR ' . $gateway . ' phải là PNG, JPG hoặc WebP.');
        }

        $uploadDir = BASE_PATH . '/public/uploads/payment-qr/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Không thể tạo thư mục lưu ảnh QR.');
        }

        $fileName = $gateway . '_qr_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mimeType];
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            throw new \RuntimeException('Không thể lưu ảnh QR ' . $gateway . '.');
        }

        return '/uploads/payment-qr/' . $fileName;
    }
}