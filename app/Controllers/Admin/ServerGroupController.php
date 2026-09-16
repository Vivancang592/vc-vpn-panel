<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\ServerGroup;

class ServerGroupController extends BaseController
{
    private ServerGroup $serverGroupModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->serverGroupModel = new ServerGroup();
    }

    public function index(): void
    {
        $groups = $this->serverGroupModel->getAll();
        $this->render('admin.server-groups.index', [
            'activeMenu' => 'server-groups',
            'groups'     => $groups
        ]);
    }

    public function showCreate(): void
    {
        $this->render('admin.server-groups.create', [
            'activeMenu' => 'server-groups'
        ]);
    }

    public function create(): void
    {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            $_SESSION['error'] = 'Tên nhóm máy chủ không được để trống!';
            $this->redirect('/admin/server-groups/create');
            return;
        }

        $this->serverGroupModel->create([
            'name'        => $name,
            'description' => $description,
            'status'      => $status
        ]);

        $_SESSION['flash_message'] = 'Tạo nhóm máy chủ thành công!';
        $this->redirect('/admin/server-groups');
    }

    public function showEdit(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $group = $id ? $this->serverGroupModel->find($id) : null;

        if (!$group) {
            $_SESSION['error'] = 'Nhóm máy chủ không tồn tại!';
            $this->redirect('/admin/server-groups');
            return;
        }

        $this->render('admin.server-groups.edit', [
            'activeMenu' => 'server-groups',
            'group'      => $group
        ]);
    }

    public function edit(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!$id || empty($name)) {
            $_SESSION['error'] = 'Tên nhóm máy chủ không được để trống!';
            $this->redirect('/admin/server-groups/edit?id=' . $id);
            return;
        }

        $this->serverGroupModel->update($id, [
            'name'        => $name,
            'description' => $description,
            'status'      => $status
        ]);

        $_SESSION['flash_message'] = 'Cập nhật nhóm máy chủ thành công!';
        $this->redirect('/admin/server-groups');
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $this->serverGroupModel->delete($id);
            $_SESSION['flash_message'] = 'Đã xóa nhóm máy chủ thành công!';
        } else {
            $_SESSION['error'] = 'Mã nhóm máy chủ không hợp lệ!';
        }
        $this->redirect('/admin/server-groups');
    }
}