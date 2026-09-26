<?php

namespace App\Controllers;

use App\Models\VpnPlan;
use App\Models\Post;

class HomeController extends BaseController
{
    public function index(): void
    {
        // Người dùng đã đăng nhập thì đưa thẳng vào bảng điều khiển
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $plans = [];
        if (class_exists('App\\Models\\VpnPlan')) {
            $planModel = new VpnPlan();
            $plans = $planModel->getAllActive();
        }

        $this->render('home.index', [
            'activeMenu' => 'home',
            'plans' => $plans,
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function plans(): void
    {
        $plans = []; 
        if (class_exists('App\Models\VpnPlan')) {
            $planModel = new VpnPlan();
            $plans = $planModel->getAllActive();
        }

        $this->render('home.plans', [
            'activeMenu' => 'plans',
            'plans' => $plans,
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function download(): void
    {
        $this->render('home.download', [
            'activeMenu' => 'download',
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

     public function client(): void
{
    $downloads = [
        // Android
        'android-stable' => 'https://github.com/KaringX/karing/releases/download/v1.2.25.2802/karing_1.2.25.2802_android_arm64-v8a.apk',

        // iOS
        'ios-stable' => 'https://apps.apple.com/vn/iphone/search?term=karing',

        // Windows
        'windows-stable' => 'https://github.com/KaringX/karing/releases/download/v1.2.25.2802/karing_1.2.25.2802_windows_x64.exe',

        // macOS
        'macos-stable' => 'https://github.com/KaringX/karing/releases/download/v1.2.25.2802/karing_1.2.25.2802_macos_universal.dmg',

        // Linux
        'linux-stable' => 'https://github.com/KaringX/karing/releases/download/v1.2.25.2802/karing_1.2.25.2802_linux_amd64.AppImage',
    ];

    $tag = trim((string) ($_GET['tag'] ?? ''));

    if ($tag === '' || !isset($downloads[$tag])) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 | Link tải ứng dụng không tồn tại.';
        return;
    }

    $downloadUrl = $downloads[$tag];

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Location: ' . $downloadUrl, true, 302);
    exit;
}  


    public function faq(): void
    {
        $posts = [];
        if (class_exists('App\Models\Post')) {
            $postModel = new Post();
            // Lấy danh sách bài viết/hướng dẫn từ cơ sở dữ liệu
            $posts = array_values(array_filter($postModel->getAllPublished(), fn ($item) => ($item['type'] ?? '') === 'tutorial'));
        }

        $this->render('home.faq', [
            'activeMenu' => 'faq',
            'posts' => $posts,
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function postDetail(): void
    {
        $slug = $_GET['slug'] ?? '';
        $postId = (int) ($_GET['id'] ?? 0);
        $post = null;

        if ((!empty($slug) || $postId > 0) && class_exists('App\Models\Post')) {
            $postModel = new Post();
            $post = !empty($slug) ? $postModel->getBySlug($slug) : $postModel->findWithAuthor($postId);
            $relatedPosts = array_values(array_filter($postModel->getAllPublished(), fn ($item) =>
                ($item['id'] ?? 0) !== ($post['id'] ?? 0)
                && ($item['type'] ?? '') === 'tutorial'
            ));
        }

        $this->render('home.post-detail', [
            'activeMenu' => 'faq',
            'post' => $post,
            'relatedPosts' => $relatedPosts ?? [],
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function terms(): void
    {
        $this->render('policies.terms', [
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function privacy(): void
    {
        $this->render('policies.privacy', [
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

    public function refund(): void
    {
        $this->render('policies.refund', [
            'showSidebar' => !empty($_SESSION['user_id'])
        ]);
    }

}
