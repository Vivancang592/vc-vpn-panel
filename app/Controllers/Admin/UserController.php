<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\User;

class UserController extends BaseController
{
    private User $userModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
            $this->redirect('/login');
        }
        $this->userModel = new User();
    }

    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $role = trim($_GET['role'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $users = $this->userModel->getAll($search, $role, $status);

        $this->render('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'activeMenu' => 'users'
        ]);
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            $balance = (float)($_POST['balance'] ?? 0);

            if (empty($username) || empty($email) || empty($password)) {
                $_SESSION['flash_message'] = 'Vui lòng điền đầy đủ Tên đăng nhập, Email và Mật khẩu!';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/users/create');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_message'] = 'Định dạng Email không hợp lệ!';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/users/create');
            }

            if ($this->userModel->findByUsernameOrEmail($username) || $this->userModel->findByUsernameOrEmail($email)) {
                $_SESSION['flash_message'] = 'Tên đăng nhập hoặc Email đã tồn tại trên hệ thống!';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/users/create');
            }

            $refCode = strtoupper(substr(md5(uniqid($username, true)), 0, 8));
            $data = [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'role' => $role,
                'status' => $status,
                'balance' => $balance,
                'commission_balance' => 0.00,
                'ref_code' => $refCode,
                'created_by' => $_SESSION['user_id']
            ];

            if ($this->userModel->create($data)) {
                $this->logActivity('CREATE_USER', 'Tạo người dùng: ' . $username);
                $_SESSION['flash_message'] = 'Thêm thành viên mới thành công!';
                $_SESSION['flash_type'] = 'success';
                $this->redirect('/admin/users');
            } else {
                $_SESSION['flash_message'] = 'Lỗi hệ thống, không thể thêm thành viên!';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/users/create');
            }
        }

        $this->render('admin.users.create', ['activeMenu' => 'users']);
    }

    public function edit(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $user = $this->userModel->findById($id);

        if (!$user) {
            $_SESSION['flash_message'] = 'Không tìm thấy thành viên!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/users');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $role = $_POST['role'] ?? $user['role'];
            $status = $_POST['status'] ?? $user['status'];
            $balance = (float)($_POST['balance'] ?? $user['balance']);
            $commissionBalance = (float)($_POST['commission_balance'] ?? $user['commission_balance']);
            $newPassword = $_POST['new_password'] ?? '';

            if ($id === (int)$_SESSION['user_id'] && $role !== 'admin') {
                $_SESSION['flash_message'] = 'Bảo mật: Bạn không thể tự hạ vai trò Admin của chính mình!';
                $_SESSION['flash_type'] = 'danger';
                $this->redirect('/admin/users/edit?id=' . $id);
            }

            $updateData = [
                'role' => $role,
                'status' => $status,
                'balance' => $balance,
                'commission_balance' => $commissionBalance
            ];

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $_SESSION['flash_message'] = 'Mật khẩu mới phải có tối thiểu 6 ký tự!';
                    $_SESSION['flash_type'] = 'danger';
                    $this->redirect('/admin/users/edit?id=' . $id);
                }
                $updateData['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
            }

            if ($this->userModel->update($id, $updateData)) {
                $this->logActivity('UPDATE_USER', 'Cập nhật người dùng #' . $id . ' (' . $user['username'] . ')');
                $_SESSION['flash_message'] = 'Cập nhật thông tin người dùng thành công!';
                $_SESSION['flash_type'] = 'success';
                $this->redirect('/admin/users');
            } else {
                $_SESSION['flash_message'] = 'Không thể cập nhật thông tin!';
                $_SESSION['flash_type'] = 'danger';
            }
        }

        $this->render('admin.users.edit', [
            'user' => $user,
            'activeMenu' => 'users'
        ]);
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $user = $this->userModel->findById($id);

        if (!$user) {
            $_SESSION['flash_message'] = 'Không tìm thấy thành viên!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/users');
        }

        $this->render('admin.users.detail', [
            'user' => $user,
            'activeMenu' => 'users'
        ]);
    }

    public function delete(): void
    {
        if ($this->denyStaff('/admin/users')) {
            return;
        }

        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $targetUser = $this->userModel->findById($id);

        if (!$targetUser) {
            $_SESSION['flash_message'] = 'Không tìm thấy tài khoản cần xóa!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/users');
        }

        if ($targetUser['role'] === 'admin') {
            $_SESSION['flash_message'] = 'Cảnh báo bảo mật: Tất cả tài khoản Quản trị viên (Admin) không thể xóa!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/users');
        }

        if ($id === (int)$_SESSION['user_id']) {
            $_SESSION['flash_message'] = 'Cảnh báo bảo mật: Bạn không thể tự xóa tài khoản của chính mình!';
            $_SESSION['flash_type'] = 'danger';
            $this->redirect('/admin/users');
        }

        if ($this->userModel->delete($id)) {
            $this->logActivity('DELETE_USER', 'Xóa người dùng #' . $id . ' (' . $targetUser['username'] . ')');
            $_SESSION['flash_message'] = 'Đã xóa người dùng thành công!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Lỗi hệ thống, không thể xóa người dùng!';
            $_SESSION['flash_type'] = 'danger';
        }
        $this->redirect('/admin/users');
    }
}
