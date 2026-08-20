<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Services\VpnAccountService;
use App\Services\RechargeService;
use App\Services\TicketService;
use App\Services\TutorialService;

class AdminController
{
    public function index()
    {
        $db = Database::getInstance();

        $totalUsers = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_admin = 0");
        $totalAccounts = $db->fetchColumn("SELECT COUNT(*) FROM vpn_accounts");
        $activeAccounts = $db->fetchColumn("SELECT COUNT(*) FROM vpn_accounts WHERE status = 'enabled'");
        $expiredAccounts = $db->fetchColumn("SELECT COUNT(*) FROM vpn_accounts WHERE status = 'disabled' AND expire_time < NOW()");
        $totalRevenue = $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = 'paid'");
        $totalCards = $db->fetchColumn("SELECT COUNT(*) FROM recharge_cards");
        $unusedCards = $db->fetchColumn("SELECT COUNT(*) FROM recharge_cards WHERE status = 'unused'");
        $usedCards = $db->fetchColumn("SELECT COUNT(*) FROM recharge_cards WHERE status = 'used'");

        // 今日统计
        $todayRevenue = $db->fetchColumn(
            "SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = 'paid' AND DATE(paid_at) = CURDATE()"
        );
        $todayNewUsers = $db->fetchColumn(
            "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND is_admin = 0"
        );
        $todayNewAccounts = $db->fetchColumn(
            "SELECT COUNT(*) FROM vpn_accounts WHERE DATE(created_at) = CURDATE()"
        );

        // 近7天订单趋势
        $orderTrend = $db->fetchAll(
            "SELECT DATE(paid_at) as date, COUNT(*) as count, COALESCE(SUM(amount), 0) as revenue
             FROM orders WHERE status = 'paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(paid_at) ORDER BY date ASC"
        );

        // 最近订单
        $recentOrders = $db->fetchAll(
            "SELECT o.*, u.email, v.username as vpn_username 
             FROM orders o 
             LEFT JOIN users u ON o.user_id = u.id 
             LEFT JOIN vpn_accounts v ON o.vpn_account_id = v.id 
             ORDER BY o.created_at DESC LIMIT 10"
        );

        // 过期账户
        $expiringAccounts = $db->fetchAll(
            "SELECT v.*, u.email 
             FROM vpn_accounts v 
             LEFT JOIN users u ON v.user_id = u.id 
             WHERE v.status = 'enabled' AND v.expire_time <= DATE_ADD(NOW(), INTERVAL 3 DAY)
             ORDER BY v.expire_time ASC LIMIT 10"
        );

        echo View::render('admin/dashboard', [
            'totalUsers' => $totalUsers,
            'totalAccounts' => $totalAccounts,
            'activeAccounts' => $activeAccounts,
            'expiredAccounts' => $expiredAccounts,
            'totalRevenue' => $totalRevenue,
            'totalCards' => $totalCards,
            'unusedCards' => $unusedCards,
            'usedCards' => $usedCards,
            'todayRevenue' => $todayRevenue,
            'todayNewUsers' => $todayNewUsers,
            'todayNewAccounts' => $todayNewAccounts,
            'orderTrend' => $orderTrend,
            'recentOrders' => $recentOrders,
            'expiringAccounts' => $expiringAccounts,
        ]);
    }

    // ========== 用户管理 ==========

    public function users()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($_GET['search'] ?? '');
        $query = "SELECT u.*, v.username as vpn_username, v.status as vpn_status, v.expire_time 
                   FROM users u 
                   LEFT JOIN vpn_accounts v ON u.id = v.user_id 
                   WHERE u.is_admin = 0";
        $params = [];

        if ($search) {
            $query .= " AND (u.email LIKE ? OR u.phone LIKE ? OR v.username LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }

        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM users u LEFT JOIN vpn_accounts v ON u.id = v.user_id WHERE u.is_admin = 0" .
            ($search ? " AND (u.email LIKE ? OR u.phone LIKE ? OR v.username LIKE ?)" : ""),
            $params
        );

        $query .= " ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $users = $db->fetchAll($query, $params);

        $totalPages = ceil($total / $perPage);

        echo View::render('admin/users', [
            'users' => $users,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
        ]);
    }

    public function toggleUserStatus($params)
    {
        $userId = (int) $params['id'];
        $db = Database::getInstance();

        $user = $db->fetch("SELECT * FROM users WHERE id = ? AND is_admin = 0", [$userId]);
        if (!$user) {
            View::json(['success' => false, 'message' => '用户不存在']);
        }

        $newStatus = $user['status'] == 1 ? 0 : 1;
        $db->update('users', ['status' => $newStatus], 'id = ?', [$userId]);

        // 同步VPN账户状态
        $vpnAccount = $db->fetch("SELECT * FROM vpn_accounts WHERE user_id = ?", [$userId]);
        if ($vpnAccount) {
            $vpnService = new VpnAccountService();
            try {
                if ($newStatus == 0) {
                    $vpnService->disableAccount($vpnAccount['id']);
                } else {
                    $vpnService->enableAccount($vpnAccount['id']);
                }
            } catch (\Exception $e) {
            }
        }

        View::json(['success' => true, 'message' => $newStatus == 1 ? '用户已启用' : '用户已禁用']);
    }

    public function adjustBalance($params)
    {
        $userId = (int) $params['id'];
        $amount = (float) ($_POST['amount'] ?? 0);
        $action = $_POST['action'] ?? 'add';

        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            View::json(['success' => false, 'message' => '用户不存在']);
        }

        if ($action === 'add') {
            $newBalance = $user['balance'] + abs($amount);
        } else {
            $newBalance = $user['balance'] - abs($amount);
            if ($newBalance < 0) $newBalance = 0;
        }

        $db->update('users', ['balance' => $newBalance], 'id = ?', [$userId]);

        $db->insert('admin_logs', [
            'user_id' => Auth::id(),
            'action' => 'adjust_balance',
            'target' => "user:{$userId}",
            'detail' => ($action === 'add' ? '增加' : '扣除') . '余额 ' . abs($amount) . ' 元',
            'ip' => Auth::getClientIp(),
        ]);

        View::json(['success' => true, 'message' => '余额调整成功', 'balance' => $newBalance]);
    }

    // ========== 套餐管理 ==========

    public function packages()
    {
        $db = Database::getInstance();
        $packages = $db->fetchAll("SELECT * FROM packages ORDER BY sort_order ASC, id ASC");

        // 为每个套餐加载定价方案
        foreach ($packages as &$pkg) {
            $pkg['pricings'] = $db->fetchAll(
                "SELECT * FROM package_pricing WHERE package_id = ? ORDER BY sort_order ASC",
                [$pkg['id']]
            );
        }
        unset($pkg);

        echo View::render('admin/packages', [
            'packages' => $packages,
        ]);
    }

    public function createPackage()
    {
        $db = Database::getInstance();
        $db->insert('packages', [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'up_rate' => (int) ($_POST['up_rate'] ?? 10240),
            'down_rate' => (int) ($_POST['down_rate'] ?? 10240),
            'active_num' => (int) ($_POST['active_num'] ?? 3),
            'data_limit' => (int) ($_POST['data_limit'] ?? 0),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'radius_profile' => trim($_POST['radius_profile'] ?? ''),
            'status' => (int) ($_POST['status'] ?? 1),
        ]);

        View::json(['success' => true, 'message' => '套餐创建成功, 请添加定价方案']);
    }

    public function updatePackage($params)
    {
        $id = (int) $params['id'];
        $db = Database::getInstance();

        $db->update('packages', [
            'name' => trim($_POST['name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'up_rate' => (int) ($_POST['up_rate'] ?? 10240),
            'down_rate' => (int) ($_POST['down_rate'] ?? 10240),
            'active_num' => (int) ($_POST['active_num'] ?? 3),
            'data_limit' => (int) ($_POST['data_limit'] ?? 0),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'radius_profile' => trim($_POST['radius_profile'] ?? ''),
            'status' => (int) ($_POST['status'] ?? 1),
        ], 'id = ?', [$id]);

        View::json(['success' => true, 'message' => '套餐更新成功']);
    }

    public function deletePackage($params)
    {
        $id = (int) $params['id'];
        $db = Database::getInstance();

        $inUse = $db->fetchColumn("SELECT COUNT(*) FROM vpn_accounts WHERE package_id = ?", [$id]);
        if ($inUse > 0) {
            View::json(['success' => false, 'message' => '该套餐正在使用中, 无法删除']);
        }

        $db->delete('package_pricing', 'package_id = ?', [$id]);
        $db->delete('packages', 'id = ?', [$id]);
        View::json(['success' => true, 'message' => '套餐已删除']);
    }

    // ========== 套餐定价管理 ==========

    public function createPricing()
    {
        $db = Database::getInstance();
        $db->insert('package_pricing', [
            'package_id' => (int) ($_POST['package_id'] ?? 0),
            'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
            'duration_days' => (int) ($_POST['duration_days'] ?? 30),
            'price' => (float) ($_POST['price'] ?? 0),
            'original_price' => !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null,
            'is_popular' => (int) ($_POST['is_popular'] ?? 0),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'status' => (int) ($_POST['status'] ?? 1),
        ]);
        View::json(['success' => true, 'message' => '定价方案创建成功']);
    }

    public function updatePricing($params)
    {
        $id = (int) $params['id'];
        $db = Database::getInstance();
        $db->update('package_pricing', [
            'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
            'duration_days' => (int) ($_POST['duration_days'] ?? 30),
            'price' => (float) ($_POST['price'] ?? 0),
            'original_price' => !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null,
            'is_popular' => (int) ($_POST['is_popular'] ?? 0),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'status' => (int) ($_POST['status'] ?? 1),
        ], 'id = ?', [$id]);
        View::json(['success' => true, 'message' => '定价方案更新成功']);
    }

    public function deletePricing($params)
    {
        $id = (int) $params['id'];
        $db = Database::getInstance();
        $db->delete('package_pricing', 'id = ?', [$id]);
        View::json(['success' => true, 'message' => '定价方案已删除']);
    }

    // ========== VPN账户管理 ==========

    public function vpnAccounts()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $query = "SELECT v.*, u.email, p.name as package_name 
                   FROM vpn_accounts v 
                   LEFT JOIN users u ON v.user_id = u.id 
                   LEFT JOIN packages p ON v.package_id = p.id 
                   WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND (v.username LIKE ? OR u.email LIKE ?)";
            $params = ["%$search%", "%$search%"];
        }

        if ($status) {
            $query .= " AND v.status = ?";
            $params[] = $status;
        }

        $countQuery = "SELECT COUNT(*) FROM vpn_accounts v LEFT JOIN users u ON v.user_id = u.id WHERE 1=1";
        if ($search) {
            $countQuery .= " AND (v.username LIKE ? OR u.email LIKE ?)";
        }
        if ($status) {
            $countQuery .= " AND v.status = ?";
        }
        $total = $db->fetchColumn($countQuery, $params);

        $query .= " ORDER BY v.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $accounts = $db->fetchAll($query, $params);

        $totalPages = ceil($total / $perPage);

        echo View::render('admin/vpn-accounts', [
            'accounts' => $accounts,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function toggleVpnAccount($params)
    {
        $accountId = (int) $params['id'];
        $vpnService = new VpnAccountService();

        $db = Database::getInstance();
        $account = $db->fetch("SELECT * FROM vpn_accounts WHERE id = ?", [$accountId]);
        if (!$account) {
            View::json(['success' => false, 'message' => 'VPN账户不存在']);
        }

        try {
            if ($account['status'] === 'enabled') {
                $vpnService->disableAccount($accountId);
                View::json(['success' => true, 'message' => 'VPN账户已禁用']);
            } else {
                $vpnService->enableAccount($accountId);
                View::json(['success' => true, 'message' => 'VPN账户已启用']);
            }
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function adminResetPassword($params)
    {
        $accountId = (int) $params['id'];
        $vpnService = new VpnAccountService();

        try {
            $customPassword = trim($_POST['password'] ?? '');
            if ($customPassword !== '') {
                $password = $vpnService->changePassword($accountId, $customPassword);
            } else {
                $password = $vpnService->resetPassword($accountId);
            }
            View::json(['success' => true, 'message' => '密码修改成功', 'password' => $password]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function adminResetTraffic($params)
    {
        $accountId = (int) $params['id'];
        $vpnService = new VpnAccountService();

        try {
            $vpnService->resetTraffic($accountId);
            View::json(['success' => true, 'message' => '流量已重置']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 管理员修改VPN账户到期时间
     */
    public function adminUpdateExpireTime($params)
    {
        $accountId = (int) $params['id'];
        $newExpireTime = trim($_POST['expire_time'] ?? '');

        if (empty($newExpireTime)) {
            View::json(['success' => false, 'message' => '请输入到期时间']);
        }

        try {
            $vpnService = new VpnAccountService();
            $result = $vpnService->adminUpdateExpireTime($accountId, $newExpireTime);

            View::json([
                'success' => true,
                'message' => "到期时间已修改为 {$newExpireTime}",
                'new_expire_time' => $newExpireTime,
                'status' => $result['status'],
            ]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== 卡密管理 ==========

    public function cards()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $batchNo = trim($_GET['batch'] ?? '');

        $query = "SELECT * FROM recharge_cards WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND card_no LIKE ?";
            $params[] = "%$search%";
        }
        if ($status) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        if ($batchNo) {
            $query .= " AND batch_no = ?";
            $params[] = $batchNo;
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM recharge_cards WHERE 1=1" .
            ($search ? " AND card_no LIKE ?" : "") .
            ($status ? " AND status = ?" : "") .
            ($batchNo ? " AND batch_no = ?" : ""),
            $params
        );

        $query .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $cards = $db->fetchAll($query, $params);

        $totalPages = ceil($total / $perPage);

        // 获取所有批次
        $batches = $db->fetchAll(
            "SELECT batch_no, COUNT(*) as count, 
                    SUM(CASE WHEN status='unused' THEN 1 ELSE 0 END) as unused,
                    SUM(CASE WHEN status='used' THEN 1 ELSE 0 END) as used,
                    MIN(created_at) as created_at
             FROM recharge_cards WHERE batch_no IS NOT NULL 
             GROUP BY batch_no ORDER BY created_at DESC LIMIT 20"
        );

        echo View::render('admin/cards', [
            'cards' => $cards,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
            'status' => $status,
            'batchNo' => $batchNo,
            'batches' => $batches,
        ]);
    }

    public function generateCards()
    {
        $amount = (float) ($_POST['amount'] ?? 0);
        $count = (int) ($_POST['count'] ?? 0);
        $expireDays = (int) ($_POST['expire_days'] ?? 0);

        if ($amount <= 0 || $count <= 0) {
            View::json(['success' => false, 'message' => '请输入正确的金额和数量']);
        }

        if ($count > 1000) {
            View::json(['success' => false, 'message' => '单次最多生成1000张卡密']);
        }

        try {
            $rechargeService = new RechargeService();
            $result = $rechargeService->generateCards($amount, $count, $expireDays);

            $db = Database::getInstance();
            $db->insert('admin_logs', [
                'user_id' => Auth::id(),
                'action' => 'generate_cards',
                'target' => $result['batch_no'],
                'detail' => "生成 {$count} 张面值 {$amount} 元的卡密",
                'ip' => Auth::getClientIp(),
            ]);

            View::json(['success' => true, 'message' => "成功生成 {$count} 张卡密", 'cards' => $result['cards'], 'batch_no' => $result['batch_no']]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => '生成失败: ' . $e->getMessage()]);
        }
    }

    public function exportCards()
    {
        $batchNo = $_GET['batch'] ?? '';
        if (!$batchNo) {
            View::redirect('/admin/cards');
        }

        $db = Database::getInstance();
        $cards = $db->fetchAll("SELECT card_no, amount, status FROM recharge_cards WHERE batch_no = ? ORDER BY id", [$batchNo]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment;filename="cards_' . $batchNo . '.csv"');

        echo "\xEF\xBB\xBF";
        echo "卡密,面值,状态\n";
        $statusMap = ['unused' => '未使用', 'used' => '已使用', 'disabled' => '已禁用'];
        foreach ($cards as $card) {
            echo "{$card['card_no']},{$card['amount']},{$statusMap[$card['status']]}\n";
        }
        exit;
    }

    // ========== 订单管理 ==========

    public function orders()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $query = "SELECT o.*, u.email, v.username as vpn_username 
                   FROM orders o 
                   LEFT JOIN users u ON o.user_id = u.id 
                   LEFT JOIN vpn_accounts v ON o.vpn_account_id = v.id 
                   WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND (o.order_no LIKE ? OR u.email LIKE ? OR v.username LIKE ?)";
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        if ($status) {
            $query .= " AND o.status = ?";
            $params[] = $status;
        }

        $countQuery = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN vpn_accounts v ON o.vpn_account_id = v.id WHERE 1=1";
        if ($search) {
            $countQuery .= " AND (o.order_no LIKE ? OR u.email LIKE ? OR v.username LIKE ?)";
        }
        if ($status) {
            $countQuery .= " AND o.status = ?";
        }
        $total = $db->fetchColumn($countQuery, $params);

        $query .= " ORDER BY o.created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $orders = $db->fetchAll($query, $params);

        $totalPages = ceil($total / $perPage);

        echo View::render('admin/orders', [
            'orders' => $orders,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
            'status' => $status,
        ]);
    }

    // ========== 系统设置 ==========

    public function settings()
    {
        $db = Database::getInstance();
        $settings = $db->fetchAll("SELECT * FROM settings ORDER BY key_name");

        echo View::render('admin/settings', [
            'settings' => $settings,
        ]);
    }

    public function saveSettings()
    {
        $db = Database::getInstance();
        $settings = $_POST['settings'] ?? [];
        if (isset($settings['site_template']) && !in_array($settings['site_template'], ['default', 'modern', 'dark', 'cloud'], true)) {
            $settings['site_template'] = 'default';
        }
        if (isset($settings['admin_layout']) && !in_array($settings['admin_layout'], ['topbar', 'sidebar'], true)) {
            $settings['admin_layout'] = 'topbar';
        }

        foreach ($settings as $key => $value) {
            $existing = $db->fetch("SELECT key_name FROM settings WHERE key_name = ?", [$key]);
            if ($existing) {
                $db->update('settings', ['value' => $value], 'key_name = ?', [$key]);
            } else {
                $db->insert('settings', ['key_name' => $key, 'value' => $value]);
            }
        }

        View::json(['success' => true, 'message' => '设置已保存']);
    }

    // ========== 操作日志 ==========

    public function logs()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 30;
        $offset = ($page - 1) * $perPage;

        $total = $db->fetchColumn("SELECT COUNT(*) FROM admin_logs");
        $logs = $db->fetchAll(
            "SELECT l.*, u.email 
             FROM admin_logs l 
             LEFT JOIN users u ON l.user_id = u.id 
             ORDER BY l.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
        );

        $totalPages = ceil($total / $perPage);

        echo View::render('admin/logs', [
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    // ========== 工单管理 ==========

    public function tickets()
    {
        $service = new TicketService();
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $result = $service->getAllTickets((int)($_GET['page'] ?? 1), 20, $status, $category);

        echo View::render('admin/tickets', [
            'tickets' => $result['data'],
            'page' => $result['page'],
            'totalPages' => ceil($result['total'] / $result['perPage']),
            'total' => $result['total'],
            'status' => $status,
            'category' => $category,
        ]);
    }

    public function ticketDetail($params)
    {
        $service = new TicketService();
        try {
            $detail = $service->getTicketDetail((int)$params['id'], Auth::id(), true);
            echo View::render('admin/ticket-detail', $detail);
        } catch (\Exception $e) {
            View::redirect('/admin/tickets');
        }
    }

    public function ticketReply($params)
    {
        $service = new TicketService();
        try {
            $service->replyTicket((int)$params['id'], Auth::id(), trim($_POST['content'] ?? ''), true);
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

    // ========== 教程管理 ==========

    public function tutorials()
    {
        $service = new TutorialService();
        $tutorials = $service->getAllTutorials();
        $categories = $service->getCategories();

        echo View::render('admin/tutorials', [
            'tutorials' => $tutorials,
            'categories' => $categories,
        ]);
    }

    public function createTutorial()
    {
        $service = new TutorialService();
        try {
            $service->createTutorial($_POST);
            View::json(['success' => true, 'message' => '教程创建成功']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function updateTutorial($params)
    {
        $service = new TutorialService();
        try {
            $service->updateTutorial((int)$params['id'], $_POST);
            View::json(['success' => true, 'message' => '教程更新成功']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteTutorial($params)
    {
        $service = new TutorialService();
        try {
            $service->deleteTutorial((int)$params['id']);
            View::json(['success' => true, 'message' => '教程已删除']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== AFF管理 ==========

    public function aff()
    {
        $affService = new \App\Services\AffService();
        $stats = $affService->getAllStats();

        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $tab = $_GET['tab'] ?? 'commissions';

        if ($tab === 'withdrawals') {
            $list = $db->fetchAll(
                "SELECT w.*, u.email FROM aff_withdrawals w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM aff_withdrawals");
        } else {
            $list = $db->fetchAll(
                "SELECT c.*, u.email as invited_email, r.email as referrer_email FROM aff_commissions c LEFT JOIN users u ON c.invited_user_id = u.id LEFT JOIN users r ON c.referrer_id = r.id ORDER BY c.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM aff_commissions");
        }

        $totalPages = ceil($total / $perPage);

        echo View::render('admin/aff', [
            'stats' => $stats,
            'list' => $list,
            'tab' => $tab,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    public function affApproveCommission()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $affService = new \App\Services\AffService();
        try {
            $affService->approveCommission($id);
            View::json(['success' => true, 'message' => '佣金已审核通过']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function affApproveWithdrawal()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $affService = new \App\Services\AffService();
        try {
            $affService->approveWithdrawal($id);
            View::json(['success' => true, 'message' => '提现已通过']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function affRejectWithdrawal()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $affService = new \App\Services\AffService();
        try {
            $affService->rejectWithdrawal($id);
            View::json(['success' => true, 'message' => '提现已驳回, 佣金已退回']);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ========== 优惠码管理 ==========

    public function coupons()
    {
        $db = Database::getInstance();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $search = trim($_GET['search'] ?? '');
        $query = "SELECT * FROM coupon_codes WHERE 1=1";
        $params = [];
        if ($search) {
            $query .= " AND (code LIKE ? OR name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $total = $db->fetchColumn("SELECT COUNT(*) FROM coupon_codes" . ($search ? " WHERE code LIKE ? OR name LIKE ?" : ""), $search ? ["%$search%", "%$search%"] : []);

        $query .= " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $coupons = $db->fetchAll($query, $params);
        $totalPages = ceil($total / $perPage);

        echo View::render('admin/coupons', [
            'coupons' => $coupons,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'search' => $search,
        ]);
    }

    public function createCoupon()
    {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $discountType = $_POST['discount_type'] ?? 'fixed';
        $discountValue = (float) ($_POST['discount_value'] ?? 0);
        $minAmount = (float) ($_POST['min_amount'] ?? 0);
        $packageId = (int) ($_POST['package_id'] ?? 0);
        $pricingId = (int) ($_POST['pricing_id'] ?? 0);
        $startsAt = trim($_POST['starts_at'] ?? '');
        $expiresAt = trim($_POST['expires_at'] ?? '');
        $status = (int) ($_POST['status'] ?? 1);

        if (empty($code) || $discountValue <= 0) {
            View::json(['success' => false, 'message' => '请填写优惠码和折扣值']);
        }

        $db = Database::getInstance();
        $existing = $db->fetch("SELECT id FROM coupon_codes WHERE code = ?", [$code]);
        if ($existing) {
            View::json(['success' => false, 'message' => '优惠码已存在']);
        }

        $db->insert('coupon_codes', [
            'code' => $code,
            'name' => $name ?: null,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'min_amount' => $minAmount,
            'package_id' => $packageId > 0 ? $packageId : null,
            'pricing_id' => $pricingId > 0 ? $pricingId : null,
            'starts_at' => $startsAt ?: null,
            'expires_at' => $expiresAt ?: null,
            'status' => $status,
        ]);

        $db->insert('admin_logs', [
            'user_id' => Auth::id(),
            'action' => 'create_coupon',
            'target' => $code,
            'detail' => "创建优惠码 {$code} ({$discountType}: {$discountValue})",
            'ip' => Auth::getClientIp(),
        ]);

        View::json(['success' => true, 'message' => '优惠码创建成功']);
    }

    public function updateCoupon($params)
    {
        $id = (int) $params['id'];
        $status = (int) ($_POST['status'] ?? 1);

        $db = Database::getInstance();
        $db->update('coupon_codes', ['status' => $status], 'id = ?', [$id]);

        View::json(['success' => true, 'message' => '优惠码状态已更新']);
    }

    public function deleteCoupon($params)
    {
        $id = (int) $params['id'];
        $db = Database::getInstance();
        $db->delete('coupon_codes', 'id = ?', [$id]);

        View::json(['success' => true, 'message' => '优惠码已删除']);
    }
}
