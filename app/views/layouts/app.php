<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName ?? 'VPN商店') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <?php $layoutTheme = \App\Core\View::getSetting('admin_layout', 'topbar'); ?>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --dark: #1e293b;
            --gray: #64748b;
            --light: #f1f5f9;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 250px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            margin: 0;
            color: var(--dark);
        }
        .navbar-brand { font-weight: 700; color: var(--primary) !important; }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--dark);
            color: #cbd5e1;
            padding: 0;
            z-index: 1000;
            transition: transform 0.3s;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 1.5rem 1.25rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header i { color: var(--primary-light); }
        .sidebar .nav-link {
            color: #cbd5e1 !important;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.05);
            color: #fff !important;
        }
        .sidebar .nav-link.active {
            background: rgba(99,102,241,0.15);
            border-left-color: var(--primary);
            color: #fff !important;
        }
        .sidebar .nav-section {
            padding: 0.75rem 1.25rem 0.25rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        body.layout-topbar .sidebar {
            position: relative;
            top: auto;
            right: auto;
            bottom: auto;
            left: auto;
            width: 100%;
            height: auto;
            min-height: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            overflow: visible;
            background: #fff;
            color: var(--dark);
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(15, 23, 42, .06);
        }
        body.layout-topbar .sidebar-header {
            color: var(--dark);
            border-bottom: 0;
            padding: 1rem 1.5rem;
            white-space: nowrap;
        }
        body.layout-topbar .sidebar-header i { color: var(--primary); }
        body.layout-topbar .sidebar .nav-section { display: none; }
        body.layout-topbar .sidebar ul.nav { display: contents; }
        body.layout-topbar .sidebar ul.nav li.nav-item { display: inline-flex; }
        body.layout-topbar .sidebar .nav-link { color: var(--gray) !important; border-left: 0; border-bottom: 3px solid transparent; padding: 1.15rem .75rem; white-space: nowrap; }
        body.layout-topbar .sidebar .nav-link:hover { background: #f8fafc; color: var(--dark) !important; }
        body.layout-topbar .sidebar .nav-link.active { background: #eef2ff; color: var(--primary-dark) !important; border-bottom-color: var(--primary); }
        body.layout-topbar .main-content { margin-left: auto; margin-right: auto; max-width: 1440px; padding: 2rem; }
        body.layout-topbar .sidebar > .nav-section + ul { margin-left: auto; }
        body.layout-topbar .sidebar .nav-section.mt-3 { display: none; }
        body.layout-topbar .sidebar > ul:last-child { margin-left: auto; }
        body.layout-topbar .sidebar > ul { margin-top: 0 !important; margin-bottom: 0 !important; }
        body.layout-topbar .sidebar > .nav-section + ul,
        body.layout-topbar .sidebar > ul:last-child { align-items: center; }
        body.layout-topbar .sidebar > .nav-section + ul + .nav-section + ul { margin-left: 0; }
        body.layout-topbar .sidebar > .nav-section + ul + .nav-section + ul .nav-link { padding-left: .75rem; padding-right: .75rem; }
        body.layout-topbar .sidebar .nav-item:last-child .nav-link { color: var(--danger) !important; }
        body.layout-topbar .mobile-menu-button { display: none; }
        body.layout-sidebar .mobile-menu-button { display: none; }
        body.layout-topbar .mobile-menu-button { order: 3; margin-left: auto; }
        .mobile-menu-button { border: 0; background: transparent; font-size: 1.35rem; }
        @media (max-width: 768px) {
            body.layout-topbar .sidebar { display: block; }
            body.layout-topbar .sidebar ul.nav { display: none; }
            body.layout-topbar .sidebar.show ul.nav { display: flex; }
            body.layout-topbar .sidebar-header { display: flex; justify-content: space-between; }
        }
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .card-body { padding: 1.5rem; }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .stat-value { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .stat-label { color: var(--gray); font-size: 0.85rem; margin: 0; }
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .badge { font-weight: 500; }
        .table { margin: 0; }
        .table th { font-weight: 600; color: var(--gray); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .page-link { color: var(--primary); border-radius: 8px; margin: 0 2px; border: none; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .topbar h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .alert-banner {
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .vpn-credential-box {
            background: var(--dark);
            color: #e2e8f0;
            padding: 1.5rem;
            border-radius: 0.75rem;
            font-family: 'Courier New', monospace;
            position: relative;
        }
        .vpn-credential-box .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 24px; transition: 0.3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        input:checked + .toggle-slider { background: var(--success); }
        input:checked + .toggle-slider:before { transform: translateX(20px); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
    <?= isset($content_css) ? $content_css : '' ?>
</head>
<body class="layout-<?= htmlspecialchars($layoutTheme === 'sidebar' ? 'sidebar' : 'topbar') ?>">
    <div class="sidebar" id="sidebar">
        <button class="mobile-menu-button d-md-none" onclick="toggleSidebar()" aria-label="打开导航"><i class="bi bi-list"></i></button>
        <div class="sidebar-header">
            <i class="bi bi-shield-lock"></i> <?= htmlspecialchars($siteName ?? 'VPN商店') ?>
        </div>
        <?php if (isset($currentUser) && $currentUser): ?>
            <?php if ($currentUser['is_admin']): ?>
                <div class="nav-section">管理后台</div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/admin"><i class="bi bi-speedometer2"></i> 仪表盘</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/users"><i class="bi bi-people"></i> 用户管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/vpn-accounts"><i class="bi bi-shield-check"></i> VPN账户</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/packages"><i class="bi bi-box-seam"></i> 套餐管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/orders"><i class="bi bi-receipt"></i> 订单管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/aff"><i class="bi bi-megaphone"></i> 推广管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/cards"><i class="bi bi-ticket"></i> 卡密管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/coupons"><i class="bi bi-tag"></i> 优惠码</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/tickets"><i class="bi bi-life-preserver"></i> 工单管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/tutorials"><i class="bi bi-book"></i> 教程管理</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/logs"><i class="bi bi-journal-text"></i> 操作日志</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/settings"><i class="bi bi-gear"></i> 系统设置</a></li>
                </ul>
                <div class="nav-section mt-3">用户面板</div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/dashboard"><i class="bi bi-house"></i> 我的面板</a></li>
                    <li class="nav-item"><a class="nav-link" href="/vpn-account"><i class="bi bi-shield-lock"></i> VPN账户</a></li>
                    <li class="nav-item"><a class="nav-link" href="/orders"><i class="bi bi-clock-history"></i> 订单记录</a></li>
                    <li class="nav-item"><a class="nav-link" href="/recharge"><i class="bi bi-wallet2"></i> 充值</a></li>
                    <li class="nav-item"><a class="nav-link" href="/aff"><i class="bi bi-megaphone"></i> 推广赚钱</a></li>
                </ul>
            <?php else: ?>
                <div class="nav-section">用户中心</div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="/dashboard"><i class="bi bi-house"></i> 我的面板</a></li>
                    <li class="nav-item"><a class="nav-link" href="/vpn-account"><i class="bi bi-shield-lock"></i> VPN账户</a></li>
                    <li class="nav-item"><a class="nav-link" href="/packages"><i class="bi bi-box-seam"></i> 购买套餐</a></li>
                    <li class="nav-item"><a class="nav-link" href="/orders"><i class="bi bi-clock-history"></i> 订单记录</a></li>
                    <li class="nav-item"><a class="nav-link" href="/recharge"><i class="bi bi-wallet2"></i> 余额充值</a></li>
                    <li class="nav-item"><a class="nav-link" href="/aff"><i class="bi bi-megaphone"></i> 推广赚钱</a></li>
                    <li class="nav-item"><a class="nav-link" href="/tickets"><i class="bi bi-life-preserver"></i> 我的工单</a></li>
                    <li class="nav-item"><a class="nav-link" href="/profile"><i class="bi bi-person"></i> 个人设置</a></li>
                </ul>
            <?php endif; ?>
            <div class="nav-section mt-3">其他</div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-shop"></i> 商城首页</a></li>
                <li class="nav-item"><a class="nav-link" href="/tutorials"><i class="bi bi-book"></i> 使用教程</a></li>
                <li class="nav-item"><a class="nav-link" href="/logout"><i class="bi bi-box-arrow-right"></i> 退出登录</a></li>
            </ul>
        <?php else: ?>
            <ul class="nav flex-column mt-3">
                <li class="nav-item"><a class="nav-link" href="/"><i class="bi bi-shop"></i> 商城首页</a></li>
                <li class="nav-item"><a class="nav-link" href="/tutorials"><i class="bi bi-book"></i> 使用教程</a></li>
                <li class="nav-item"><a class="nav-link" href="/login"><i class="bi bi-box-arrow-in-right"></i> 登录</a></li>
                <li class="nav-item"><a class="nav-link" href="/register"><i class="bi bi-person-plus"></i> 注册</a></li>
            </ul>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <?php if (!empty($siteAnnouncement)): ?>
            <div class="alert alert-warning alert-banner" role="alert">
                <i class="bi bi-megaphone"></i> <?= htmlspecialchars($siteAnnouncement) ?>
            </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }
        // AJAX helper
        async function apiPost(url, data) {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?? "" ?>');
            const response = await fetch(url, { method: 'POST', body: formData });
            return await response.json();
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('已复制到剪贴板');
            });
        }
        function showToast(msg, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 p-3 rounded shadow';
            toast.style.cssText = `background:${type==='success'?'#10b981':'#ef4444'};color:#fff;z-index:9999;min-width:200px;`;
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        // 流量格式化
        function formatTrafficBytes(bytes) {
            if (!bytes || bytes <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
        }
        // 时长格式化
        function formatDuration(seconds) {
            if (!seconds || seconds <= 0) return '-';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return h + '小时' + m + '分';
            if (m > 0) return m + '分' + s + '秒';
            return s + '秒';
        }
    </script>
</body>
</html>
