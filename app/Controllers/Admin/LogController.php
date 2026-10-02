<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SystemLog;
use App\Models\AccessLog;
use App\Models\EmailLog;
use App\Models\ChatEvent;

class LogController extends BaseController
{
    /** Số bản ghi hiển thị trên mỗi trang của các tab log. */
    private const LOGS_PER_PAGE = 10;

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
        $pagination = $this->buildPagination($this->systemLogModel->countAll());

        $this->renderLogTab('system', [
            'logs' => $this->systemLogModel->allWithUser(self::LOGS_PER_PAGE, $pagination['offset']),
            'pagination' => $pagination
        ]);
    }

    public function access(): void
    {
        $pagination = $this->buildPagination($this->accessLogModel->countAll());

        $this->renderLogTab('access', [
            'logs' => $this->accessLogModel->allWithUser(self::LOGS_PER_PAGE, $pagination['offset']),
            'pagination' => $pagination
        ]);
    }

    public function email(): void
    {
        $pagination = $this->buildPagination($this->emailLogModel->countAll());

        $this->renderLogTab('email', [
            'logs' => $this->emailLogModel->all(self::LOGS_PER_PAGE, $pagination['offset']),
            'pagination' => $pagination
        ]);
    }

    public function chatbot(): void
    {
        $pagination = $this->buildPagination($this->chatEventModel->countAll());

        $this->renderLogTab('chatbot', [
            'logs' => $this->chatEventModel->allWithSessionContext(self::LOGS_PER_PAGE, $pagination['offset']),
            'pagination' => $pagination
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

        $logFiles = [
            __DIR__ . '/../../../storage/logs/macrodroid_debug.log',
            __DIR__ . '/../../../storage/logs/webhook.log',
        ];
        $cleared = false;
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                file_put_contents($logFile, '');
                $cleared = true;
            }
        }
        if ($cleared) {
            $_SESSION['flash_message'] = 'Đã xóa sạch nội dung nhật ký Webhook (SePay / MacroDroid)!';
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
        $page = max(1, (int) ($_POST['page'] ?? 1));
        $pageSuffix = $page > 1 ? '?page=' . $page : '';
        $logSources = [
            'system' => ['model' => $this->systemLogModel, 'url' => '/admin/logs/system'],
            'access' => ['model' => $this->accessLogModel, 'url' => '/admin/logs/access'],
            'email'  => ['model' => $this->emailLogModel, 'url' => '/admin/logs/email'],
            'chatbot' => ['model' => $this->chatEventModel, 'url' => '/admin/logs/chatbot']
        ];
        $redirectUrl = isset($logSources[$logType])
            ? $logSources[$logType]['url'] . $pageSuffix
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

    /**
     * Tính thông số phân trang từ tổng số bản ghi.
     * Trả về kèm 'offset' để truy vấn đúng trang hiện tại.
     */
    private function buildPagination(int $total): array
    {
        $pages = max(1, (int) ceil($total / self::LOGS_PER_PAGE));
        $page  = min($pages, max(1, (int) ($_GET['page'] ?? 1)));

        return [
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'offset' => ($page - 1) * self::LOGS_PER_PAGE,
        ];
    }

    private function renderLogTab(string $activeLogTab, array $data = []): void
    {
        $this->render('admin.logs.index', array_merge([
            'activeMenu'  => 'logs',
            'activeLogTab' => $activeLogTab,
            'logs'         => [],
            'logContent'   => '',
            'pagination'   => []
        ], $data));
    }
}
