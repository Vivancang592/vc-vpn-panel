<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Server;
use App\Models\ServerGroup;

class ServerController extends BaseController
{
    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
    }

    public function index(): void
    {
        $serverModel = new Server();
        $servers = $serverModel->getAll();

        $this->render('admin.servers.index', [
            'servers' => $servers
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $serverModel = new Server();
        $server = $serverModel->findById($id);

        if (!$server) {
            $_SESSION['error'] = 'Không tìm thấy máy chủ này.';
            $this->redirect('/admin/servers');
        }

        $this->render('admin.servers.detail', [
            'server' => $server
        ]);
    }

    public function showCreate(): void
    {
        $groupModel = new ServerGroup();
        $groups = $groupModel->getAll();

        $this->render('admin.servers.create', [
            'groups' => $groups
        ]);
    }

    public function create(): void
    {
        $name = trim($_POST['name'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);
        $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
        $location = trim($_POST['location'] ?? '');
        $ipAddress = trim($_POST['ip_address'] ?? '');
        $apiPort = (int)($_POST['api_port'] ?? 80);
        $apiToken = trim($_POST['api_token'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (empty($name) || empty($ipAddress) || empty($countryCode) || empty($location) || $groupId <= 0) {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ Tên máy chủ, Nhóm, Mã quốc gia, Vị trí và Địa chỉ IP.';
            $this->redirect('/admin/servers/create');
        }

        $serverModel = new Server();
        $success = $serverModel->create([
            'group_id'     => $groupId,
            'name'         => $name,
            'country_code' => $countryCode,
            'location'     => $location,
            'ip_address'   => $ipAddress,
            'api_port'     => $apiPort,
            'api_token'    => $apiToken ?: null,
            'status'       => $status
        ]);

        if ($success) {
            $_SESSION['success'] = 'Thêm máy chủ mới thành công.';
            $this->redirect('/admin/servers');
        } else {
            $_SESSION['error'] = 'Có lỗi xảy ra khi lưu dữ liệu. Vui lòng thử lại.';
            $this->redirect('/admin/servers/create');
        }
    }

    public function showEdit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $serverModel = new Server();
        $server = $serverModel->findById($id);

        if (!$server) {
            $_SESSION['error'] = 'Không tìm thấy máy chủ này.';
            $this->redirect('/admin/servers');
        }

        $groupModel = new ServerGroup();
        $groups = $groupModel->getAll();

        $this->render('admin.servers.edit', [
            'server' => $server,
            'groups' => $groups
        ]);
    }

    public function edit(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        
        $name = trim($_POST['name'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);
        $countryCode = strtoupper(trim($_POST['country_code'] ?? ''));
        $location = trim($_POST['location'] ?? '');
        $ipAddress = trim($_POST['ip_address'] ?? '');
        $apiPort = (int)($_POST['api_port'] ?? 80);
        $apiToken = trim($_POST['api_token'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if (empty($name) || empty($ipAddress) || empty($countryCode) || empty($location) || $groupId <= 0) {
            $_SESSION['error'] = 'Vui lòng điền đầy đủ Tên máy chủ, Nhóm, Mã quốc gia, Vị trí và IP.';
            $this->redirect('/admin/servers/edit?id=' . $id);
        }

        $serverModel = new Server();
        $success = $serverModel->update($id, [
            'group_id'     => $groupId,
            'name'         => $name,
            'country_code' => $countryCode,
            'location'     => $location,
            'ip_address'   => $ipAddress,
            'api_port'     => $apiPort,
            'api_token'    => $apiToken ?: null,
            'status'       => $status
        ]);

        if ($success) {
            $_SESSION['success'] = 'Cập nhật thông tin máy chủ thành công.';
            $this->redirect('/admin/servers');
        } else {
            $_SESSION['error'] = 'Không có thay đổi nào hoặc có lỗi xảy ra.';
            $this->redirect('/admin/servers/edit?id=' . $id);
        }
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $serverModel = new Server();
        
        if ($id > 0 && $serverModel->delete($id)) {
            $_SESSION['success'] = 'Đã xóa máy chủ thành công.';
        } else {
            $_SESSION['error'] = 'Lỗi khi xóa máy chủ. Vui lòng thử lại.';
        }
        
        $this->redirect('/admin/servers');
    }

    public function sync(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $serverModel = new Server();
        $server = $serverModel->findById($id);

        if (!$server) {
            $_SESSION['flash_message'] = 'Máy chủ không tồn tại!';
            $_SESSION['flash_type']    = 'danger';
            $this->redirect('/admin/servers');
            return;
        }

        $groupId = (int)($server['group_id'] ?? 0);
        if ($groupId <= 0) {
            $_SESSION['flash_message'] = 'Máy chủ chưa thuộc Nhóm nào!';
            $_SESSION['flash_type']    = 'warning';
            $this->redirect('/admin/servers');
            return;
        }

        if (class_exists('App\Models\NodeTask') && class_exists('App\Models\Subscription')) {
            $subModel  = new \App\Models\Subscription();
            $taskModel = new \App\Models\NodeTask();

            $activeSubs = $subModel->getActiveByGroupId($groupId);

            $count = 0;
            foreach ($activeSubs as $sub) {
                $taskModel->create([
                    'server_id' => $id,
                    'action'    => 'add_user',
                    'payload'   => [
                        'username'        => 'sub_' . $sub['id'],
                        'uuid'            => $sub['uuid'],
                        'transfer_enable' => (int)$sub['transfer_enable'],
                        'end_date'        => $sub['end_date']
                    ]
                ]);
                $count++;
            }

            $_SESSION['flash_message'] = "Đã gửi {$count} task đồng bộ tài khoản xuống máy chủ thành công!";
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Không thể khởi tạo mô hình đồng bộ task!';
            $_SESSION['flash_type']    = 'danger';
        }

        $this->redirect('/admin/servers');
    }
}