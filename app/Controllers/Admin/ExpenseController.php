<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Expense;

class ExpenseController extends BaseController
{
    private Expense $expenseModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->expenseModel = new Expense();
    }

    public function index(): void
    {
        $expenses = $this->expenseModel->all();

        $this->render('admin.expenses.index', [
            'activeMenu' => 'expenses',
            'expenses'   => $expenses
        ]);
    }

    // GET /admin/expenses/create
    public function showCreate(): void
    {
        $this->render('admin.expenses.create', [
            'activeMenu' => 'expenses'
        ]);
    }

    // POST /admin/expenses/create
    public function create(): void
    {
        $title       = trim($_POST['title'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $category    = trim($_POST['category'] ?? '');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
        $note        = trim($_POST['note'] ?? '');

        if (empty($title) || $amount <= 0 || empty($expenseDate)) {
            $_SESSION['error'] = 'Vui lòng nhập tên khoản chi, số tiền và ngày chi hợp lệ!';
            $this->redirect('/admin/expenses/create');
            return;
        }

        $data = [
            'title'        => $title,
            'amount'       => $amount,
            'category'     => $category,
            'expense_date' => $expenseDate,
            'note'         => $note,
            'created_at'   => date('Y-m-d H:i:s')
        ];

        if ($this->expenseModel->create($data)) {
            $_SESSION['flash_message'] = 'Thêm khoản chi phí thành công!';
            $_SESSION['flash_type']    = 'success';
            $this->redirect('/admin/expenses');
        } else {
            $_SESSION['error'] = 'Lỗi hệ thống, không thể lưu khoản chi!';
            $this->redirect('/admin/expenses/create');
        }
    }

    // GET /admin/expenses/edit
    public function showEdit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $expense = $this->expenseModel->find($id);

        if (!$expense) {
            $_SESSION['error'] = 'Khoản chi phí không tồn tại!';
            $this->redirect('/admin/expenses');
            return;
        }

        $this->render('admin.expenses.edit', [
            'activeMenu' => 'expenses',
            'expense'    => $expense
        ]);
    }

    // POST /admin/expenses/edit
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $expense = $this->expenseModel->find($id);

        if (!$expense) {
            $_SESSION['error'] = 'Khoản chi phí không tồn tại!';
            $this->redirect('/admin/expenses');
            return;
        }

        $title       = trim($_POST['title'] ?? '');
        $amount      = (float)($_POST['amount'] ?? 0);
        $category    = trim($_POST['category'] ?? '');
        $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
        $note        = trim($_POST['note'] ?? '');

        if (empty($title) || $amount <= 0 || empty($expenseDate)) {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ thông tin hợp lệ!';
            $this->redirect('/admin/expenses/edit?id=' . $id);
            return;
        }

        $data = [
            'title'        => $title,
            'amount'       => $amount,
            'category'     => $category,
            'expense_date' => $expenseDate,
            'note'         => $note
        ];

        if ($this->expenseModel->update($id, $data)) {
            $_SESSION['flash_message'] = 'Cập nhật khoản chi phí thành công!';
            $_SESSION['flash_type']    = 'success';
            $this->redirect('/admin/expenses');
        } else {
            $_SESSION['error'] = 'Không thể cập nhật thông tin chi phí!';
            $this->redirect('/admin/expenses/edit?id=' . $id);
        }
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

        if ($id > 0) {
            if ($this->expenseModel->delete($id)) {
                $_SESSION['flash_message'] = 'Đã xóa khoản chi phí thành công!';
                $_SESSION['flash_type']    = 'success';
            } else {
                $_SESSION['error'] = 'Không thể xóa khoản chi phí này!';
            }
        } else {
            $_SESSION['error'] = 'Khoản chi phí không hợp lệ!';
        }

        $this->redirect('/admin/expenses');
    }
}