<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\OrderService;
use App\Services\RechargeService;
use App\Services\VpnAccountService;
use App\Services\TicketService;
use App\Services\TutorialService;
use App\Services\PaymentService;
use App\Payments\PaymentGatewayFactory;

class HomeController
{
    public function index()
    {
        $db = Database::getInstance();
        $packages = $db->fetchAll("SELECT * FROM packages WHERE status = 1 ORDER BY sort_order ASC, id ASC");

        foreach ($packages as &$pkg) {
            $pkg['default_pricing'] = $db->fetch(
                "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY is_popular DESC, sort_order ASC, id ASC LIMIT 1",
                [$pkg['id']]
            );
        }
        unset($pkg);

        // 兼容首页直接购买: 只展示默认定价, 但仍保留套餐页里的完整周期选择
        foreach ($packages as &$pkg) {
            $pkg['selected_pricing_id'] = $pkg['default_pricing']['id'] ?? 0;
        }
        unset($pkg);

        $announcement = View::getSetting('site_announcement', '');
        $notice = View::getSetting('site_notice', '欢迎使用VPN服务');

        echo View::render('home/index', [
            'packages' => $packages,
            'announcement' => $announcement,
            'notice' => $notice,
        ]);
    }

    public function login()
    {
        if (Auth::check()) {
            View::redirect('/dashboard');
        }

        echo View::render('auth/login', [], 'layouts/blank');
    }

    public function doLogin()
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            View::json(['success' => false, 'message' => '请填写邮箱和密码']);
        }

        if (Auth::attempt($email, $password)) {
            View::json(['success' => true, 'redirect' => '/dashboard']);
        }

        View::json(['success' => false, 'message' => '邮箱或密码错误']);
    }

    public function register()
    {
        if (Auth::check()) {
            View::redirect('/dashboard');
        }

        echo View::render('auth/register', [], 'layouts/blank');
    }

    public function doRegister()
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (empty($email) || empty($password)) {
            View::json(['success' => false, 'message' => '请填写邮箱和密码']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            View::json(['success' => false, 'message' => '邮箱格式不正确']);
        }

        if (strlen($password) < 6) {
            View::json(['success' => false, 'message' => '密码至少6位']);
        }

        if ($password !== $confirmPassword) {
            View::json(['success' => false, 'message' => '两次密码不一致']);
        }

        try {
            $refCode = trim($_POST['ref_code'] ?? '') ?: null;
            Auth::register($email, $password, $phone, $refCode);
            View::json(['success' => true, 'redirect' => '/login', 'message' => '注册成功, 请登录']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function logout()
    {
        Auth::logout();
        View::redirect('/login');
    }

    public function dashboard()
    {
        $userId = Auth::id();
        $db = Database::getInstance();

        $vpnAccount = $db->fetch("SELECT * FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC LIMIT 1", [$userId]);
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

        // 同步该用户所有VPN账户状态
        $vpnService = new VpnAccountService();
        $allAccounts = $db->fetchAll("SELECT id FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC", [$userId]);
        foreach ($allAccounts as $acc) {
            try {
                $synced = $vpnService->syncAccountStatus($acc['id']);
                // 用第一个账号的信息给页面展示
                if ($vpnAccount && $acc['id'] == $vpnAccount['id']) {
                    $vpnAccount = $synced;
                }
            } catch (\Exception $e) {
                // 同步失败不阻断页面
            }
        }

        $recentOrders = $db->fetchAll(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
            [$userId]
        );

        $packages = $db->fetchAll("SELECT * FROM packages WHERE status = 1 ORDER BY sort_order ASC");

        echo View::render('dashboard/index', [
            'vpnAccount' => $vpnAccount,
            'user' => $user,
            'recentOrders' => $recentOrders,
            'packages' => $packages,
        ]);
    }

    public function checkout($params)
    {
        $packageId = (int) ($params['id'] ?? 0);
        $db = Database::getInstance();

        $package = $db->fetch("SELECT * FROM packages WHERE id = ? AND status = 1", [$packageId]);
        if (!$package) {
            View::redirect('/packages');
        }

        $pricings = $db->fetchAll(
            "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY is_popular DESC, sort_order ASC, id ASC",
            [$packageId]
        );

        $userAccounts = $db->fetchAll(
            "SELECT id, username, remark FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
            [Auth::id()]
        );

        $user = $db->fetch("SELECT balance FROM users WHERE id = ?", [Auth::id()]);
        $gateways = PaymentGatewayFactory::getEnabledGateways();

        echo View::render('home/checkout', [
            'package' => $package,
            'pricings' => $pricings,
            'userAccounts' => $userAccounts,
            'gateways' => $gateways,
            'userBalance' => (float) $user['balance'],
        ]);
    }

    public function verifyCoupon()
    {
        $code = trim($_POST['code'] ?? '');
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $pricingId = (int) ($_POST['pricing_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);

        try {
            $couponService = new \App\Services\CouponService();
            $result = $couponService->validateCoupon($code, $packageId, $pricingId, $amount);
            View::json([
                'success' => true,
                'message' => "优惠码有效, 减免 ¥{$result['discount_amount']}",
                'discount_amount' => $result['discount_amount'],
                'final_amount' => $result['final_amount'],
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function packages()
    {
        $db = Database::getInstance();
        $packages = $db->fetchAll("SELECT * FROM packages WHERE status = 1 ORDER BY sort_order ASC, id ASC");

        // 为每个套餐加载定价方案(月付/季付/年付)
        foreach ($packages as &$pkg) {
            $pkg['pricings'] = $db->fetchAll(
                "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY sort_order ASC",
                [$pkg['id']]
            );
        }
        unset($pkg);

        $vpnAccount = null;
        $userAccounts = [];
        if (Auth::check()) {
            $vpnAccount = $db->fetch("SELECT * FROM vpn_accounts WHERE user_id = ?", [Auth::id()]);
            $userAccounts = $db->fetchAll(
                "SELECT id, username, remark FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
                [Auth::id()]
            );
        }

        // 获取已启用的支付网关
        $gateways = PaymentGatewayFactory::getEnabledGateways();

        echo View::render('home/packages', [
            'packages' => $packages,
            'vpnAccount' => $vpnAccount,
            'userAccounts' => $userAccounts,
            'gateways' => $gateways,
        ]);
    }

    public function buyPackage($params)
    {
        $packageId = (int) ($params['id'] ?? 0);
        $pricingId = (int) ($_POST['pricing_id'] ?? 0);
        $payMethod = $_POST['pay_method'] ?? 'balance';
        $gatewayMethod = $_POST['gateway_method'] ?? '';
        $targetAccountId = (int) ($_POST['target_account_id'] ?? 0); // 0=创建新子账号, >0=续费指定账号
        $couponCode = trim($_POST['coupon_code'] ?? '');

        $db = Database::getInstance();
        $package = $db->fetch("SELECT * FROM packages WHERE id = ? AND status = 1", [$packageId]);
        if (!$package) {
            View::json(['success' => false, 'message' => '套餐不存在']);
        }

        // 如果指定了定价ID, 验证
        if ($pricingId > 0) {
            $pricing = $db->fetch(
                "SELECT * FROM package_pricing WHERE id = ? AND package_id = ? AND status = 1",
                [$pricingId, $packageId]
            );
            if (!$pricing) {
                View::json(['success' => false, 'message' => '定价方案不存在']);
            }
        } else {
            // 兼容旧逻辑: 获取第一个定价
            $pricing = $db->fetch(
                "SELECT * FROM package_pricing WHERE package_id = ? AND status = 1 ORDER BY sort_order ASC LIMIT 1",
                [$packageId]
            );
            if (!$pricing) {
                View::json(['success' => false, 'message' => '该套餐没有可用的定价方案']);
            }
        }

        // 先校验优惠码并计算折扣
        $discountAmount = 0;
        if ($couponCode !== '') {
            try {
                $couponService = new \App\Services\CouponService();
                $coupon = $couponService->validateCoupon($couponCode, $packageId, $pricingId, (float) $pricing['price']);
                $discountAmount = (float) ($coupon['discount_amount'] ?? 0);
            } catch (\Exception $e) {
                View::json(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        $finalPrice = max(0, (float) $pricing['price'] - $discountAmount);

        // 余额支付
        if ($payMethod === 'balance') {
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [Auth::id()]);

            if ($user['balance'] < $finalPrice) {
                View::json(['success' => false, 'message' => '余额不足, 请先充值或选择在线支付', 'redirect' => '/recharge']);
            }

            try {
                $orderService = new OrderService();
                $result = $orderService->createOrderWithPricing(Auth::id(), $packageId, $pricingId, $targetAccountId ?: null, $discountAmount, $couponCode ?: null);

                // 记录AFF佣金
                try {
                    $affService = new \App\Services\AffService();
                    $affService->recordCommission($result['order_id'] ?? 0, Auth::id(), $finalPrice);
                } catch (\Exception $e) {}

                View::json(['success' => true, 'message' => '购买成功!', 'redirect' => '/dashboard']);
            } catch (\Exception $e) {
                View::json(['success' => false, 'message' => $e->getMessage()]);
            }
            return;
        }

        // 第三方支付 (pockyt / payssion)
        if (in_array($payMethod, ['pockyt', 'payssion'])) {
            if (empty($gatewayMethod)) {
                View::json(['success' => false, 'message' => '请选择支付方式']);
            }

            try {
                $paymentService = new PaymentService();
                $result = $paymentService->createPaymentOrder(
                    Auth::id(),
                    $packageId,
                    $pricingId,
                    $payMethod,
                    $gatewayMethod,
                    $targetAccountId ?: null,
                    $discountAmount,
                    $couponCode ?: null
                );

                View::json([
                    'success' => true,
                    'message' => '正在跳转支付页面...',
                    'pay_url' => $result['pay_url'],
                    'payment_no' => $result['payment_no'],
                    'order_no' => $result['order_no'],
                    'redirect' => '/payment/waiting?payment_no=' . $result['payment_no'],
                ]);
            } catch (\Exception $e) {
                View::json(['success' => false, 'message' => $e->getMessage()]);
            }
            return;
        }

        View::json(['success' => false, 'message' => '不支持的支付方式']);
    }

    // ========== 支付回调 ==========

    public function paymentWaiting()
    {
        Auth::requireLogin();
        $paymentNo = $_GET['payment_no'] ?? '';
        echo View::render('dashboard/payment-waiting', ['paymentNo' => $paymentNo]);
    }

    public function paymentCheck()
    {
        $paymentNo = $_GET['payment_no'] ?? '';
        if (empty($paymentNo)) {
            View::json(['status' => 'not_found']);
        }

        $paymentService = new PaymentService();
        $result = $paymentService->checkPaymentStatus($paymentNo);
        View::json($result);
    }

    public function paymentReturn()
    {
        // 支付完成后用户跳转回来
        $paymentNo = $_GET['payment_no'] ?? $_GET['outOrderNo'] ?? $_GET['track_id'] ?? '';
        if ($paymentNo) {
            // 主动查询一次
            $paymentService = new PaymentService();
            $paymentService->checkPaymentStatus($paymentNo);
        }
        View::redirect('/dashboard?paid=1');
    }

    public function paymentNotify($params)
    {
        $gateway = $params['gateway'] ?? '';
        $data = array_merge($_POST, $_GET);

        $paymentService = new PaymentService();
        $result = $paymentService->handleCallback($gateway, $data);

        // 返回给支付网关的确认响应
        if ($gateway === 'pockyt') {
            header('Content-Type: application/json');
            echo json_encode(['ret_code' => '000000', 'ret_msg' => 'success']);
        } else {
            echo 'success';
        }
        exit;
    }

    public function recharge()
    {
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [Auth::id()]);

        $cardsEnabled = View::getSetting('payment_card_enabled', '1');

        echo View::render('dashboard/recharge', [
            'user' => $user,
            'cardsEnabled' => $cardsEnabled,
        ]);
    }

    public function doRecharge()
    {
        $cardNo = trim($_POST['card_no'] ?? '');

        try {
            $rechargeService = new RechargeService();
            $result = $rechargeService->rechargeByCard(Auth::id(), $cardNo);
            View::json([
                'success' => true,
                'message' => "充值成功! 到账 {$result['amount']} 元",
                'balance' => $result['new_balance'],
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function orders()
    {
        $userId = Auth::id();
        $db = Database::getInstance();

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        $orders = $db->fetchAll(
            "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = $db->fetchColumn("SELECT COUNT(*) FROM orders WHERE user_id = ?", [$userId]);

        $totalPages = ceil($total / $perPage);

        echo View::render('dashboard/orders', [
            'orders' => $orders,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    public function vpnAccount()
    {
        $userId = Auth::id();
        $db = Database::getInstance();

        // 获取用户的所有VPN账号
        $accounts = $db->fetchAll(
            "SELECT * FROM vpn_accounts WHERE user_id = ? ORDER BY id ASC",
            [$userId]
        );

        $vpnService = new VpnAccountService();
        $subService = new \App\Services\SubscriptionService();
        $radiusClient = new \App\Core\RadiusUiClient();
        $allSessions = [];
        $accountSubscriptions = [];

        foreach ($accounts as &$account) {
            try {
                $subService->checkAndSwitchSubscription($account['id']);
                $account = $vpnService->syncAccountStatus($account['id']);
            } catch (\Exception $e) {
            }

            // 获取该账号的订阅信息
            $activeSub = null;
            $otherSubs = [];
            try {
                $activeSub = $subService->getActiveSubscription($account['id']);
                $otherSubs = $subService->getOtherSubscriptions($account['id']);
            } catch (\Exception $e) {
            }
            $accountSubscriptions[$account['id']] = [
                'active' => $activeSub,
                'others' => $otherSubs,
            ];

            try {
                $sessions = $radiusClient->getUserSessions($account['username']);
                if (!empty($sessions)) {
                    foreach ($sessions as &$s) {
                        $s['vpn_username'] = $account['username'];
                    }
                    $allSessions = array_merge($allSessions, $sessions);
                }
            } catch (\Exception $e) {
            }
        }
        unset($account);

        $packages = $db->fetchAll("SELECT * FROM packages WHERE status = 1 ORDER BY sort_order ASC, id ASC");

        echo View::render('dashboard/vpn-account', [
            'accounts' => $accounts,
            'sessions' => $allSessions,
            'packages' => $packages,
            'accountSubscriptions' => $accountSubscriptions,
        ]);
    }

    public function resetVpnPassword($params)
    {
        $accountId = (int) ($params['id'] ?? 0);
        $userId = Auth::id();

        $db = Database::getInstance();
        $account = $db->fetch("SELECT * FROM vpn_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);

        if (!$account) {
            View::json(['success' => false, 'message' => 'VPN账户不存在']);
        }

        try {
            $vpnService = new VpnAccountService();
            $customPassword = trim($_POST['password'] ?? '');

            if ($customPassword !== '') {
                $newPassword = $vpnService->changePassword($accountId, $customPassword);
            } else {
                $newPassword = $vpnService->resetPassword($accountId);
            }

            View::json([
                'success' => true,
                'message' => '密码修改成功',
                'password' => $newPassword,
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function profile()
    {
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [Auth::id()]);

        echo View::render('dashboard/profile', [
            'user' => $user,
        ]);
    }

    public function updateProfile()
    {
        $phone = trim($_POST['phone'] ?? '');
        $db = Database::getInstance();

        $db->update('users', ['phone' => $phone], 'id = ?', [Auth::id()]);

        View::json(['success' => true, 'message' => '资料更新成功']);
    }

    public function changePassword()
    {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [Auth::id()]);

        if (!password_verify($oldPassword, $user['password_hash'])) {
            View::json(['success' => false, 'message' => '原密码错误']);
        }

        if (strlen($newPassword) < 6) {
            View::json(['success' => false, 'message' => '新密码至少6位']);
        }

        if ($newPassword !== $confirmPassword) {
            View::json(['success' => false, 'message' => '两次密码不一致']);
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->update('users', ['password_hash' => $hash], 'id = ?', [Auth::id()]);

        View::json(['success' => true, 'message' => '密码修改成功']);
    }

    /**
     * 付费重置流量
     */
    public function resetTraffic($params)
    {
        $accountId = (int) ($params['id'] ?? 0);
        $userId = Auth::id();

        $db = Database::getInstance();
        $account = $db->fetch("SELECT * FROM vpn_accounts WHERE id = ? AND user_id = ?", [$accountId, $userId]);

        if (!$account) {
            View::json(['success' => false, 'message' => 'VPN账户不存在']);
        }

        try {
            $vpnService = new VpnAccountService();
            $price = $vpnService->getTrafficResetPrice();

            $result = $vpnService->purchaseTrafficReset($userId, $accountId, $price);

            View::json([
                'success' => true,
                'message' => "流量重置成功, 扣费 ¥{$price}",
                'order_no' => $result['order_no'],
                'new_balance' => $result['new_balance'],
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 用户手动切换订阅
     */
    public function switchSubscription($params)
    {
        $accountId = (int) ($params['id'] ?? 0);
        $subscriptionId = (int) ($_POST['subscription_id'] ?? 0);
        $userId = Auth::id();

        if ($subscriptionId <= 0) {
            View::json(['success' => false, 'message' => '请选择要切换的订阅']);
        }

        try {
            $subService = new \App\Services\SubscriptionService();
            $result = $subService->switchSubscription($userId, $accountId, $subscriptionId);

            View::json([
                'success' => true,
                'message' => "已切换到: {$result['package_name']}",
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== AFF推广系统 ==========

    public function aff()
    {
        Auth::requireLogin();
        $affService = new \App\Services\AffService();
        $userId = Auth::id();

        $stats = $affService->getStats($userId);
        $invites = $affService->getInviteList($userId);
        $commissions = $affService->getCommissionList($userId);
        $withdrawals = $affService->getWithdrawals($userId);

        echo View::render('dashboard/aff', [
            'stats' => $stats,
            'invites' => $invites['data'],
            'commissions' => $commissions['data'],
            'withdrawals' => $withdrawals['data'],
        ]);
    }

    public function affWithdraw()
    {
        Auth::requireLogin();
        $amount = (float) ($_POST['amount'] ?? 0);
        $method = trim($_POST['method'] ?? '');
        $account = trim($_POST['account'] ?? '');

        if ($amount <= 0 || empty($method) || empty($account)) {
            View::json(['success' => false, 'message' => '请填写完整信息']);
        }

        try {
            $affService = new \App\Services\AffService();
            $affService->requestWithdrawal(Auth::id(), $amount, $method, $account);
            View::json(['success' => true, 'message' => '提现申请已提交, 请等待审核']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== 工单系统 ==========

    public function tickets()
    {
        Auth::requireLogin();
        $service = new TicketService();
        $result = $service->getUserTickets(Auth::id(), (int)($_GET['page'] ?? 1), 10);

        echo View::render('dashboard/tickets', [
            'tickets' => $result['data'],
            'page' => $result['page'],
            'totalPages' => ceil($result['total'] / $result['perPage']),
            'total' => $result['total'],
        ]);
    }

    public function ticketCreate()
    {
        Auth::requireLogin();
        echo View::render('dashboard/ticket-create');
    }

    public function ticketStore()
    {
        $service = new TicketService();
        try {
            $result = $service->createTicket(
                Auth::id(),
                trim($_POST['subject'] ?? ''),
                $_POST['category'] ?? 'general',
                $_POST['priority'] ?? 'normal',
                trim($_POST['content'] ?? '')
            );
            View::json(['success' => true, 'message' => '工单已提交', 'redirect' => '/tickets/' . $result['id']]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ticketDetail($params)
    {
        Auth::requireLogin();
        $service = new TicketService();
        try {
            $detail = $service->getTicketDetail((int)$params['id'], Auth::id());
            echo View::render('dashboard/ticket-detail', $detail);
        } catch (\Exception $e) {
            View::redirect('/tickets');
        }
    }

    public function ticketReply($params)
    {
        $service = new TicketService();
        try {
            $service->replyTicket((int)$params['id'], Auth::id(), trim($_POST['content'] ?? ''));
            View::json(['success' => true, 'message' => '回复成功']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function ticketClose($params)
    {
        $service = new TicketService();
        try {
            $service->closeTicket((int)$params['id'], Auth::id());
            View::json(['success' => true, 'message' => '工单已关闭']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== 教程页面 ==========

    public function tutorials()
    {
        $service = new TutorialService();
        $category = $_GET['category'] ?? '';
        $tutorials = $service->listTutorials($category);
        $categories = $service->getCategories();

        echo View::render('home/tutorials', [
            'tutorials' => $tutorials,
            'categories' => $categories,
            'currentCategory' => $category,
        ]);
    }

    public function tutorialDetail($params)
    {
        $service = new TutorialService();
        $tutorial = $service->getBySlug($params['slug']);
        if (!$tutorial) {
            View::redirect('/tutorials');
        }
        $categories = $service->getCategories();
        $related = $service->listTutorials($tutorial['category']);

        echo View::render('home/tutorial-detail', [
            'tutorial' => $tutorial,
            'categories' => $categories,
            'related' => array_filter($related, fn($t) => $t['id'] != $tutorial['id']),
        ]);
    }
}
