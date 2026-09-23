<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SupportTicket;
use App\Models\TicketMessage;

class TicketController extends BaseController
{
    private SupportTicket $ticketModel;
    private TicketMessage $messageModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
            $this->redirect('/login');
        }
        $this->ticketModel = new SupportTicket();
        $this->messageModel = new TicketMessage();
    }

    public function index(): void
    {
        $tickets = $this->ticketModel->allWithDetails();

        $this->render('admin.tickets.index', [
            'activeMenu' => 'tickets',
            'tickets'    => $tickets
        ]);
    }

    public function delete(): void
    {
        if ($this->denyStaff('/admin/tickets')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/admin/tickets');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        $ticket = $this->ticketModel->findWithDetails($id);

        if (!$ticket) {
            $_SESSION['error'] = 'Ticket không tồn tại!';
            $this->redirect('/admin/tickets');
            return;
        }

        if (($ticket['status'] ?? '') !== 'closed') {
            $_SESSION['error'] = 'Chỉ có thể xóa ticket đã đóng!';
            $this->redirect('/admin/tickets');
            return;
        }

        if ($this->ticketModel->delete($id)) {
            $this->logActivity('DELETE_TICKET', 'Xóa ticket #' . $id . ' (' . ($ticket['subject'] ?? '') . ')');
            $_SESSION['flash_message'] = 'Đã xóa ticket đã đóng thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['error'] = 'Không thể xóa ticket này!';
        }

        $this->redirect('/admin/tickets');
    }

    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $ticket = $this->ticketModel->findWithDetails($id);

        if (!$ticket) {
            $_SESSION['error'] = 'Yêu cầu hỗ trợ không tồn tại!';
            $this->redirect('/admin/tickets');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $message = trim($_POST['message'] ?? '');
            $status  = $this->isStaff() ? $ticket['status'] : ($_POST['status'] ?? $ticket['status']);

            if (!empty($message)) {
                $this->messageModel->create([
                    'ticket_id'  => $id,
                    'sender_id'  => $_SESSION['user_id'],
                    'message'    => $message,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $updateData = ['status' => $status];
            if (empty($ticket['assigned_staff_id'])) {
                $updateData['assigned_staff_id'] = $_SESSION['user_id'];
            }

            $this->ticketModel->updateTicket($id, $updateData);

            $_SESSION['flash_message'] = 'Đã gửi phản hồi và cập nhật trạng thái!';
            $_SESSION['flash_type']    = 'success';
            $this->redirect('/admin/tickets/detail?id=' . $id);
            return;
        }

        $messages = $this->messageModel->getByTicketId($id);

        $this->render('admin.tickets.detail', [
            'activeMenu' => 'tickets',
            'ticket'     => $ticket,
            'messages'   => $messages
        ]);
    }
}