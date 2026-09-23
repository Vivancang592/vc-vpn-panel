<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SystemLog;
use App\Models\AccessLog;
use App\Models\EmailLog;
use App\Models\ChatEvent;

class LogController extends BaseController
{
    private SystemLog $systemLogModel;
    private AccessLog $accessLogModel;
    private EmailLog $emailLogModel;
    private ChatEvent $chatEventModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->systemLogModel = new SystemLog();
        $this->accessLogModel = new AccessLog();
        $this->emailLogModel = new EmailLog();
        $this->chatEventModel = new ChatEvent();
    }

    public function index(): void
    {
        $this->system();
    }

    public function system(): void
    {
        $this->renderLogTab('system', [
            'logs' => $this->systemLogModel->allWithUser()
        ]);
    }

    public function access(): void
    {
        $this->renderLogTab('access', [
            'logs' => $this->accessLogModel->allWithUser()
        ]);
    }

    public function email(): void
    {
        $this->renderLogTab('email', [
            'logs' => $this->emailLogModel->all()
        ]);
    }

    public function chatbot(): void
    {
        $this->renderLogTab('chatbot', [
            'logs' => $this->chatEventModel->allWithSessionContext()
        ]);
    }

    /**
     * Hiển thị nhật ký MacroDroid Webhook
     */
    public function macrodroid(): void
    {
        $logFile = __DIR__ . '/../../../storage/logs/macrodroid_debug.log';
        $logContent = '';

        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
        }

        $this->renderLogTab('macrodroid', [
            'logContent' => $logContent
        ]);
    }

    /**
     * Xóa sạch file nhật ký MacroDroid
     */
    public function clearMacrodroid(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_message'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/logs/macrodroid');
        }

        $logFile = __DIR__ . '/../../../storage/logs/macrodroid_debug.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
            $_SESSION['flash_message'] = 'Đã xóa sạch nội dung nhật ký MacroDroid!';
            $_SESSION['flash_type']    = 'success';
        }
        $this->redirect('/admin/logs/macrodroid');
    }

    /**
     * Xóa các bản ghi đã chọn hoặc toàn bộ một loại log lưu trong CSDL.
     */
    public function delete(): void
    {
        $logType = $_POST['log_type'] ?? '';
        $logSources = [
            'system' => ['model' => $this->systemLogModel, 'url' => '/admin/logs/system'],
            'access' => ['model' => $this->accessLogModel, 'url' => '/admin/logs/access'],
            'email'  => ['model' => $this->emailLogModel, 'url' => '/admin/logs/email'],
            'chatbot' => ['model' => $this->chatEventModel, 'url' => '/admin/logs/chatbot']
        ];
        $redirectUrl = isset($logSources[$logType])
            ? $logSources[$logType]['url']
            : '/admin/logs';

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['flash_message'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect($redirectUrl);
        }

        if (!isset($logSources[$logType])) {
            $_SESSION['flash_message'] = 'Loại nhật ký không hợp lệ.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/logs');
        }

        $logModel = $logSources[$logType]['model'];
        $deleteAll = ($_POST['delete_all'] ?? '') === '1';
        $logIds = $_POST['log_ids'] ?? [];
        $logIds = is_array($logIds) ? $logIds : [];

        if (!$deleteAll && empty($logIds)) {
            $_SESSION['flash_message'] = 'Vui lòng chọn ít nhất một bản ghi để xóa.';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect($redirectUrl);
        }

        try {
            $deletedCount = $deleteAll
                ? $logModel->deleteAll()
                : $logModel->deleteMany($logIds);

            $_SESSION['flash_message'] = $deletedCount > 0
                ? "Đã xóa {$deletedCount} bản ghi nhật ký."
                : 'Không tìm thấy bản ghi nào để xóa.';
            $_SESSION['flash_type'] = $deletedCount > 0 ? 'success' : 'danger';
        } catch (\Throwable $exception) {
            $_SESSION['flash_message'] = 'Không thể xóa nhật ký. Vui lòng thử lại.';
            $_SESSION['flash_type'] = 'danger';
        }

        $this->redirect($redirectUrl);
    }

    private function renderLogTab(string $activeLogTab, array $data = []): void
    {
        $this->render('admin.logs.index', array_merge([
            'activeMenu'  => 'logs',
            'activeLogTab' => $activeLogTab,
            'logs'         => [],
            'logContent'   => ''
        ], $data));
    }
}
