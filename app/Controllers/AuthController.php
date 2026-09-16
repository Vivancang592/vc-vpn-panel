<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Setting;
use App\Models\VpnPlan;
use App\Models\Subscription;
use App\Models\NodeTask;
use App\Models\AccessLog;
use App\Services\MailService;

class AuthController extends BaseController
{
    /**
     * Ghi Access Log khi đăng ký hoặc xác thực thành công mà không làm gián đoạn luồng xử lý.
     */
    private function recordAccessEvent(int $userId, string $action): void
    {
        $attribution = $_SESSION['traffic_attribution'] ?? [];
        $attribution = is_array($attribution) ? $attribution : [];

        $accessLog = new AccessLog();
        $accessLog->record(
            $userId,
            $action,
            $this->getClientIp(),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
            $attribution
        );
    }

    private function getSiteTitle(): string
    {
        $siteTitle = "VC VPN 2027";
        if (class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $siteTitle = $settingModel->get('site_title', $siteTitle) ?? $siteTitle;
        }
        return $siteTitle;
    }

    public function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);

        $siteSubtitle = "An Toàn - Bảo Mật - Uy Tín";
        if (class_exists('App\Models\Setting')) {
            $settingModel = new Setting();
            $siteSubtitle = $settingModel->get('site_description', $siteSubtitle) ?? $siteSubtitle;
        }

        $this->render('auth.login', [
            'error'        => $error,
            'siteTitle'    => $this->getSiteTitle(),
            'siteSubtitle' => $siteSubtitle
        ]);
    }

    public function login(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng thử lại.';
            $this->redirect('/login');
        }

        $now = time();
        $_SESSION['login_throttle'] = $_SESSION['login_throttle'] ?? [];
        $_SESSION['login_throttle'] = array_filter(
            $_SESSION['login_throttle'], 
            fn($timestamp) => ($now - $timestamp) < 900
        );

        if (count($_SESSION['login_throttle']) >= 5) {
            $_SESSION['error'] = 'Bạn đã nhập sai quá 5 lần. Vui lòng thử lại sau 15 phút.';
            $this->redirect('/login');
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
            $this->redirect('/login');
        }

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            
            $user = $userModel->findByUsernameOrEmailStrict($username);

            $dummyHash = '$2y$10$abcdefghijklmnopqrstuuNOPQRSTUVWXYZ0123456789abcdefgh';
            $isExactMatch = $user && (
                hash_equals($user['username'], $username) || 
                hash_equals($user['email'], $username)
            );

            if ($isExactMatch && !empty($user['password_hash'])) {
                $passwordValid = password_verify($password, $user['password_hash']);
            } else {
                password_verify($password, $dummyHash);
                $passwordValid = false;
            }

            if ($isExactMatch && $passwordValid) {
                if (($user['status'] ?? 'active') !== 'active') {
                    $_SESSION['error'] = 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.';
                    $this->redirect('/login');
                }

                session_regenerate_id(true);
                unset($_SESSION['login_throttle']);

                $clientIp = $this->getClientIp();
                $userModel->update($user['id'], [
                    'last_login_ip' => $clientIp,
                    'last_login_time' => date('Y-m-d H:i:s')
                ]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';
                $this->recordAccessEvent((int)$user['id'], 'LOGIN_PASSWORD');

                if ($_SESSION['role'] === 'admin') {
                    $this->redirect('/admin');
                } else {
                    $this->redirect('/dashboard');
                }
            }
        }

        $_SESSION['login_throttle'][] = $now;

        $_SESSION['error'] = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        $this->redirect('/login');
    }

    public function googleRedirect(): void
    {
        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? '';
        $redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?? '';

        if (empty($clientId) || empty($redirectUri)) {
            $_SESSION['error'] = 'Cấu hình Google OAuth chưa đầy đủ.';
            $this->redirect('/login');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth2_state'] = $state;

        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account'
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        $this->redirect($url);
    }

    public function googleCallback(): void
    {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';

        if (empty($state) || empty($_SESSION['oauth2_state']) || !hash_equals($_SESSION['oauth2_state'], $state)) {
            unset($_SESSION['oauth2_state']);
            $_SESSION['error'] = 'Xác thực Google không hợp lệ hoặc phiên làm việc đã hết hạn.';
            $this->redirect('/login');
        }
        unset($_SESSION['oauth2_state']);

        if (empty($code)) {
            $_SESSION['error'] = 'Đăng nhập Google thất bại hoặc bạn đã hủy thao tác.';
            $this->redirect('/login');
        }

        $clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?? '';
        $clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?? '';
        $redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?? '';

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code'
        ]));
        $response = curl_exec($ch);
        curl_close($ch);

        $tokenData = json_decode($response, true);
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            $_SESSION['error'] = 'Không thể xác thực thông tin từ Google.';
            $this->redirect('/login');
        }

        $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        $userInfoResponse = curl_exec($ch);
        curl_close($ch);

        $googleUser = json_decode($userInfoResponse, true);
        $googleId = $googleUser['sub'] ?? null;
        $email = filter_var($googleUser['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$googleId || !$email) {
            $_SESSION['error'] = 'Không lấy được thông tin email từ Google.';
            $this->redirect('/login');
        }

        $userModel = new User();
        $user = $userModel->findByGoogleId($googleId);
        $isNewGoogleUser = false;

        if (!$user) {
            $user = $userModel->findByUsernameOrEmailStrict($email);

            if ($user) {
                $userModel->update($user['id'], ['google_id' => $googleId]);
                $user['google_id'] = $googleId;
            } else {
                $usernameBase = explode('@', $email)[0];
                $usernameBase = preg_replace('/[^a-zA-Z0-9_]/', '', $usernameBase);
                if (strlen($usernameBase) < 3) {
                    $usernameBase = 'user_' . substr(md5(uniqid()), 0, 6);
                }

                $username = $usernameBase;
                $counter = 1;
                while ($userModel->findByUsernameOrEmailStrict($username)) {
                    $username = $usernameBase . '_' . $counter;
                    $counter++;
                }

                $myRefCode = strtoupper(substr(md5(uniqid($username, true)), 0, 8));
                $clientIp = $this->getClientIp();

                $created = $userModel->create([
                    'username'      => $username,
                    'email'         => $email,
                    'google_id'     => $googleId,
                    'password_hash' => null,
                    'ref_code'      => $myRefCode,
                    'register_ip'   => $clientIp
                ]);

                if ($created) {
                    $user = $userModel->findByGoogleId($googleId);
                    if ($user && isset($user['id'])) {
                        $this->activateTrialPlan((int)$user['id'], (string)($user['email'] ?? ''));
                        $isNewGoogleUser = true;
                    }
                }
            }
        }

        if (!$user) {
            $_SESSION['error'] = 'Đã có lỗi xảy ra trong quá trình khởi tạo tài khoản.';
            $this->redirect('/login');
        }

        if (($user['status'] ?? 'active') !== 'active') {
            $_SESSION['error'] = 'Tài khoản của bạn đã bị khóa hoặc chưa kích hoạt.';
            $this->redirect('/login');
        }

        session_regenerate_id(true);
        unset($_SESSION['login_throttle']);

        $clientIp = $this->getClientIp();
        $userModel->update($user['id'], [
            'last_login_ip'   => $clientIp,
            'last_login_time' => date('Y-m-d H:i:s')
        ]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'] ?? 'user';
        if ($isNewGoogleUser) {
            $this->recordAccessEvent((int)$user['id'], 'REGISTER_GOOGLE');
        }
        $this->recordAccessEvent((int)$user['id'], 'LOGIN_GOOGLE');

        if ($_SESSION['role'] === 'admin') {
            $this->redirect('/admin');
        } else {
            $this->redirect('/dashboard');
        }
    }

    public function showRegister(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }

        $error = $_SESSION['error'] ?? null;
        $success = $_SESSION['success'] ?? null;
        unset($_SESSION['error'], $_SESSION['success']);

        $this->render('auth.register', [
            'error'     => $error,
            'success'   => $success,
            'siteTitle' => $this->getSiteTitle()
        ]);
    }

    public function sendRegisterOtp(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'], 403);
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->json(['success' => false, 'message' => 'Địa chỉ Email không hợp lệ.'], 400);
        }

        $now = time();
        if (isset($_SESSION['register_otp_cooldown']) && ($now - $_SESSION['register_otp_cooldown']) < 60) {
            $this->json(['success' => false, 'message' => 'Vui lòng chờ ' . (60 - ($now - $_SESSION['register_otp_cooldown'])) . ' giây trước khi yêu cầu mã mới.'], 429);
        }

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            if ($userModel->findByUsernameOrEmailStrict($email)) {
                $this->json(['success' => false, 'message' => 'Địa chỉ Email này đã được đăng ký tài khoản.'], 400);
            }
        }

        $otpCode = (string)random_int(100000, 999999);
        $_SESSION['register_otp'] = [
            'email'      => $email,
            'code'       => $otpCode,
            'expires_at' => $now + 300
        ];
        $_SESSION['register_otp_cooldown'] = $now;

        $mailService = new MailService();
        $sent = $mailService->send($email, 'Mã xác thực đăng ký tài khoản', 'auth.register-otp', [
            'code' => $otpCode
        ]);

        if ($sent) {
            $this->json(['success' => true, 'message' => 'Mã xác thực OTP đã được gửi đến email của bạn, hãy kiểm tra hộp thư rác nếu chờ quá lâu không nhận được.']);
        } else {
            $this->json(['success' => false, 'message' => 'Không thể gửi email OTP. Vui lòng kiểm tra lại địa chỉ email hoặc cấu hình SMTP.'], 500);
        }
    }

    public function register(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ. Vui lòng thử lại.';
            $this->redirect('/register');
        }

        $username = trim($_POST['username'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $otpCode = trim($_POST['otp_code'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $refCodeInput = trim($_POST['ref_code'] ?? '');

        if (empty($username) || !$email || empty($otpCode) || empty($password)) {
            $_SESSION['error'] = 'Vui lòng điền đầy đủ các thông tin bắt buộc.';
            $this->redirect('/register');
        }

        $sessionOtp = $_SESSION['register_otp'] ?? null;
        if (!$sessionOtp || 
            !hash_equals($sessionOtp['email'], $email) || 
            !hash_equals($sessionOtp['code'], $otpCode) || 
            time() > ($sessionOtp['expires_at'] ?? 0)) {
            $_SESSION['error'] = 'Mã xác thực OTP không chính xác hoặc đã hết hạn.';
            $this->redirect('/register');
        }

        if (class_exists('App\Models\User')) {
            $userModel = new User();

            if ($userModel->findByUsernameOrEmailStrict($username)) {
                $_SESSION['error'] = 'Tên đăng nhập đã được sử dụng.';
                $this->redirect('/register');
            }

            if ($userModel->findByUsernameOrEmailStrict($email)) {
                $_SESSION['error'] = 'Email đã được sử dụng.';
                $this->redirect('/register');
            }

            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $clientIp = $this->getClientIp();
            $myRefCode = strtoupper(substr(md5(uniqid($username, true)), 0, 8));

            $referredBy = null;
            $referralBonus = 0.00;
            if (!empty($refCodeInput)) {
                $referrer = $userModel->findByRefCode($refCodeInput);
                if ($referrer) {
                    $referredBy = (int)$referrer['id'];

                    // Chỉ thưởng khi mã giới thiệu hợp lệ; số tiền được cộng trực tiếp
                    // lúc tạo tài khoản để tránh cấp thưởng nhiều lần.
                    $configuredBonus = (float)($this->settings['referral_bonus'] ?? 0);
                    if (is_finite($configuredBonus) && $configuredBonus > 0) {
                        $referralBonus = $configuredBonus;
                    }
                }
            }

            $created = $userModel->create([
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $hashedPassword,
                'ref_code'      => $myRefCode,
                'referred_by'   => $referredBy,
                'balance'       => $referralBonus,
                'register_ip'   => $clientIp
            ]);

            if ($created) {
                $newUser = $userModel->findByUsernameOrEmailStrict($email);
                if ($newUser && isset($newUser['id'])) {
                    $this->activateTrialPlan((int)$newUser['id'], (string)($newUser['email'] ?? ''));
                    $this->recordAccessEvent((int)$newUser['id'], 'REGISTER_PASSWORD');
                }
                unset($_SESSION['register_otp'], $_SESSION['register_otp_cooldown']);
                $_SESSION['success'] = 'Đăng ký tài khoản thành công. Vui lòng đăng nhập.';
                $this->redirect('/login');
            }
        }

        $_SESSION['error'] = 'Đã có lỗi xảy ra trong quá trình đăng ký. Vui lòng thử lại sau.';
        $this->redirect('/register');
    }

    public function showForgotPassword(): void
    {
        $error = $_SESSION['error'] ?? null;
        $success = $_SESSION['success'] ?? null;
        unset($_SESSION['error'], $_SESSION['success']);

        $this->render('auth.forgot-password', [
            'error'     => $error,
            'success'   => $success,
            'siteTitle' => $this->getSiteTitle()
        ]);
    }

    public function sendForgotPasswordOtp(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->json(['success' => false, 'message' => 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'], 403);
            return;
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->json(['success' => false, 'message' => 'Địa chỉ Email không hợp lệ.'], 400);
            return;
        }

        $now = time();
        if (isset($_SESSION['forgot_otp_cooldown']) && ($now - $_SESSION['forgot_otp_cooldown']) < 60) {
            $this->json(['success' => false, 'message' => 'Vui lòng chờ ' . (60 - ($now - $_SESSION['forgot_otp_cooldown'])) . ' giây trước khi yêu cầu mã mới.'], 429);
            return;
        }

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findByUsernameOrEmailStrict($email);

            if (!$user) {
                $this->json(['success' => false, 'message' => 'Địa chỉ Email này chưa được đăng ký trong hệ thống, vui lòng kiểm tra lại.'], 404);
                return;
            }

            $otpCode = (string)random_int(100000, 999999);
            $_SESSION['forgot_otp'] = [
                'email'      => $user['email'],
                'code'       => $otpCode,
                'expires_at' => $now + 300
            ];
            $_SESSION['forgot_otp_cooldown'] = $now;

            $mailService = new MailService();
            $sent = $mailService->send($user['email'], 'Mã xác thực khôi phục mật khẩu', 'auth.reset-password', [
                'code'      => $otpCode,
                'siteTitle' => $this->getSiteTitle()
            ]);

            if ($sent) {
                $this->json(['success' => true, 'message' => 'Mã xác thực OTP đã được gửi đến email của bạn, hãy kiểm tra hộp thư rác nếu chờ quá lâu không nhận được.']);
            } else {
                $this->json(['success' => false, 'message' => 'Không thể gửi email OTP. Vui lòng kiểm tra lại cấu hình SMTP.'], 500);
            }
        } else {
            $this->json(['success' => false, 'message' => 'Đã có lỗi xảy ra.'], 500);
        }
    }

    public function forgotPassword(): void
    {
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Phiên làm việc không hợp lệ.';
            $this->redirect('/forgot-password');
        }

        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $otpCode = trim($_POST['otp_code'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$email || empty($otpCode) || empty($password)) {
            $_SESSION['error'] = 'Vui lòng điền đầy đủ các thông tin bắt buộc.';
            $this->redirect('/forgot-password');
        }

        $sessionOtp = $_SESSION['forgot_otp'] ?? null;
        if (!$sessionOtp || 
            !hash_equals($sessionOtp['email'], $email) || 
            !hash_equals($sessionOtp['code'], $otpCode) || 
            time() > ($sessionOtp['expires_at'] ?? 0)) {
            $_SESSION['error'] = 'Mã xác thực OTP không chính xác hoặc đã hết hạn.';
            $this->redirect('/forgot-password');
        }

        if (class_exists('App\Models\User')) {
            $userModel = new User();
            $user = $userModel->findByUsernameOrEmailStrict($email);

            if ($user) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $userModel->update($user['id'], [
                    'password_hash' => $hashedPassword
                ]);
                unset($_SESSION['forgot_otp'], $_SESSION['forgot_otp_cooldown']);

                $_SESSION['success'] = 'Mật khẩu đã được cập nhật thành công. Vui lòng đăng nhập.';
                $this->redirect('/login');
            }
        }

        $_SESSION['error'] = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
        $this->redirect('/forgot-password');
    }

    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->recordAccessEvent((int)$_SESSION['user_id'], 'LOGOUT');
        }

        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        session_destroy();
        $this->redirect('/');
    }

    /**
     * Tự động kích hoạt gói dùng thử và khởi tạo Task gửi xuống toàn bộ máy chủ VPS
     */
    private function activateTrialPlan(int $userId, string $email = ''): void
    {
        if ($userId <= 0) {
            return;
        }

        if (!class_exists('App\Models\Setting') || 
            !class_exists('App\Models\VpnPlan') || 
            !class_exists('App\Models\Subscription')) {
            return;
        }

        $settingModel = new Setting();
        $trialEnabled = $settingModel->get('trial_enabled', '0');
        if ((string)$trialEnabled !== '1') {
            return;
        }

        $trialPlanId = (int)$settingModel->get('trial_plan_id', '0');
        if ($trialPlanId <= 0) {
            return;
        }

        $subscriptionModel = new Subscription();
        $existingSubs = $subscriptionModel->getByUserId($userId);
        if (!empty($existingSubs)) {
            return;
        }

        $planModel = new VpnPlan();
        $plan = $planModel->find($trialPlanId);
        if (!$plan || ($plan['status'] ?? '') !== 'active') {
            return;
        }

        $trialDaysConfig = $settingModel->get('trial_duration_days');
        if ($trialDaysConfig !== null && (int)$trialDaysConfig > 0) {
            $durationDays = (int)$trialDaysConfig;
        } else {
            $durationDays = (int)($plan['duration_days'] ?? 3);
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        $bandwidthGb = (int)($plan['bandwidth_limit_gb'] ?? 0);
        $transferEnable = $bandwidthGb * 1073741824;

        $now = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));

        $created = $subscriptionModel->create([
            'user_id'         => $userId,
            'plan_id'         => (int)$plan['id'],
            'order_id'        => null,
            'uuid'            => $uuid,
            'transfer_enable' => $transferEnable,
            'upload'          => 0,
            'download'        => 0,
            'start_date'      => $now,
            'end_date'        => $endDate,
            'status'          => 'active'
        ]);

        // Đẩy Task tự động xuống các máy chủ thuộc group_id của gói cước với payload chuẩn
        if ($created && class_exists('App\Models\NodeTask') && !empty($plan['group_id'])) {
            $sub = $subscriptionModel->findByUuid($uuid);
            $subId = $sub['id'] ?? 0;

            if ($subId > 0) {
                $nodeTaskModel = new NodeTask();
                $nodeTaskModel->createTasksForGroup((int)$plan['group_id'], 'add_user', [
                    'uuid'            => $uuid,
                    'end_date'        => $endDate,
                    'username'        => 'sub_' . $subId,
                    'transfer_enable' => $transferEnable
                ]);
            }
        }
    }
}
