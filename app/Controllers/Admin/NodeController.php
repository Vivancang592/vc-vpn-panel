<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NodeInbound;
use App\Models\Server;

class NodeController extends BaseController
{
    private NodeInbound $nodeModel;
    private Server $serverModel;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }
        $this->nodeModel = new NodeInbound();
        $this->serverModel = new Server();
    }

    public function index(): void
    {
        $nodes = method_exists($this->nodeModel, 'getAllWithServer') 
            ? $this->nodeModel->getAllWithServer() 
            : $this->nodeModel->getAll();

        if (!method_exists($this->nodeModel, 'getAllWithServer') && !empty($nodes)) {
            $servers = $this->serverModel->getAll();
            $serverMap = array_column($servers, null, 'id');
            foreach ($nodes as &$node) {
                $node['server_name'] = $serverMap[$node['server_id']]['name'] ?? 'Server #' . $node['server_id'];
                $node['server_ip']   = $serverMap[$node['server_id']]['ip_address'] ?? '';
            }
        }

        $this->render('admin.nodes.index', [
            'activeMenu' => 'nodes',
            'nodes'      => $nodes
        ]);
    }

    public function detail(): void
    {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $node = $id ? $this->nodeModel->find($id) : null;

        if (!$node) {
            $_SESSION['error'] = 'Nút kết nối không tồn tại!';
            $this->redirect('/admin/nodes');
            return;
        }

        $server = $this->serverModel->find($node['server_id']);
        if ($server) {
            $node['server_name']     = $server['name'];
            $node['server_ip']       = $server['ip_address'];
            $node['server_location'] = $server['location'];
        }

        $this->render('admin.nodes.detail', [
            'activeMenu' => 'nodes',
            'node'       => $node
        ]);
    }

    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $this->nodeModel->delete($id);
            $_SESSION['flash_message'] = 'Đã xóa dữ liệu nút kết nối thành công!';
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Mã nút kết nối không hợp lệ!';
            $_SESSION['flash_type']    = 'danger';
        }
        $this->redirect('/admin/nodes');
    }

    public function bulkDelete(): void
    {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids) && is_array($ids)) {
            $nodeInboundModel = new \App\Models\NodeInbound();
            $count = 0;
            foreach ($ids as $id) {
                if ($nodeInboundModel->delete((int)$id)) {
                    $count++;
                }
            }
            $_SESSION['flash_message'] = "Đã xóa thành công {$count} nút kết nối!";
            $_SESSION['flash_type']    = 'success';
        } else {
            $_SESSION['flash_message'] = 'Chưa chọn nút kết nối nào để xóa!';
            $_SESSION['flash_type']    = 'warning';
        }

        $this->redirect('/admin/nodes');
    }
}