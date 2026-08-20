<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName ?? 'VPN商店') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #7dd3fc;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 250px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(180deg, #e0f2fe 0%, #f8fafc 320px);
            background-attachment: fixed;
            margin: 0;
            color: var(--dark);
            min-height: 100vh;
        }
        /* ===== 统一骨架：左侧导航 ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(10px);
            border-right: 1px solid #e2e8f0;
            z-index: 1000;
            overflow-y: auto;
            padding: 1rem .8rem;
            transition: transform .3s;
        }
        .sidebar-header {
            display: flex; align-items: center; gap: .6rem;
            padding: .5rem .6rem 1rem;
            border-bottom: 1px solid #e0f2fe;
            margin-bottom: .6rem;
        }
        .sidebar-header i { color: var(--primary); font-size: 1.25rem; }
        .sidebar-header { font-weight: 800; color: var(--dark); font-size: 1.05rem; }
        .sidebar .nav-title {
            font-size: .68rem; text-transform: uppercase; letter-spacing: .1em;
            color: #94a3b8; margin: .9rem .6rem .3rem; font-weight: 700;
        }
        .sidebar .nav-link {
            display: flex; align-items: center; gap: .6rem;
            padding: .6rem .7rem; border-radius: 999px;
            color: var(--gray); text-decoration: none;
            font-size: .92rem; margin-bottom: 3px;
            transition: all .15s;
        }
        .sidebar .nav-link i { font-size: 1.02rem; width: 1.2rem; text-align: center; }
        .sidebar .nav-link:hover { background: var(--light); color: var(--primary-dark); }
        .sidebar .nav-link.active { background: linear-gradient(90deg, var(--primary), #38bdf8); color: #fff; box-shadow: 0 6px 14px rgba(14,165,233,.35); }
        .sidebar .nav-link.danger { color: var(--danger); }
        .sidebar .nav-link.danger:hover { background: rgba(239,68,68,.1); color: var(--danger); }
        .sidebar-toggle { display: none; border: 0; background: transparent; color: var(--dark); font-size: 1.4rem; }
        /* ===== 统一骨架：内容区 ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem 1.8rem 3rem;
            min-height: 100vh;
        }
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.4rem;
        }
        .topbar h1 { font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--dark); }
        /* 清爽云蓝视觉 */
        .card {
            border: 0;
            border-radius: 16px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 8px 24px rgba(2,132,199,.08);
        }
        .card-body { padding: 1.5rem; }
        .card-header { border-radius: 16px 16px 0 0 !important; border-bottom: 1px solid #e0f2fe; background: #f8fbfd !important; }
        .card-header.bg-light { background: #f0f9ff !important; }
        .card-header strong { color: var(--dark); }
        .stat-card { display: flex; align-items: center; gap: 1rem; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            background: linear-gradient(135deg, var(--primary), #38bdf8); color: #fff;
            box-shadow: 0 8px 18px rgba(14,165,233,.35);
        }
        .stat-icon.alt { background: linear-gradient(135deg, #10b981, #34d399); box-shadow: 0 8px 18px rgba(16,185,129,.35); }
        .stat-value { font-size: 1.45rem; font-weight: 800; margin: 0; color: var(--dark); }
        .stat-label { color: var(--gray); font-size: .84rem; margin: 0; }
        .btn { border-radius: 999px; font-weight: 600; }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #38bdf8);
            border: 0; box-shadow: 0 6px 14px rgba(14,165,233,.35);
        }
        .btn-primary:hover { filter: brightness(1.06); }
        .btn-outline-primary { --bs-btn-color: var(--primary); --bs-btn-border-color: #bae6fd; --bs-btn-hover-bg: var(--primary); }
        .badge { font-weight: 600; border-radius: 999px; }
        .table { margin: 0; }
        .table th { font-weight: 700; color: var(--gray); font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; border-bottom-color: #e2e8f0; }
        .page-link { color: var(--primary); border-radius: 999px; margin: 0 2px; border: 0; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }
        .alert-banner { border-radius: 1rem; margin-bottom: 1.5rem; }
        .vpn-credential-box {
            background: linear-gradient(135deg, #0c4a6e, #155e75);
            color: #e0f2fe; padding: 1.4rem; border-radius: 1rem;
            font-family: 'Courier New', monospace; position: relative;
        }
        .vpn-credential-box .copy-btn { position: absolute; top: .5rem; right: .5rem; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 24px; transition: .3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; }
        input:checked + .toggle-slider { background: var(--success); }
        input:checked + .toggle-slider:before { transform: translateX(20px); }
        .modal-content { border: 0; border-radius: 1rem; }
        .modal-header { border-bottom: 1px solid #e0f2fe; background: #f0f9ff; border-radius: 1rem 1rem 0 0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(14,165,233,.15); }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .sidebar-toggle { display: block; }
        }
    </style>
    <?= isset($content_css) ? $content_css : '' ?>
</head>
<body>
    <div class="sidebar" id="siteSidebar">
        <div class="sidebar-header">
            <i class="bi bi-shield-lock"></i> <?= htmlspecialchars($siteName ?? 'VPN商店') ?>
        </div>
        <button class="sidebar-toggle d-md-none position-absolute top-0 end-0 m-2 text-dark" onclick="document.getElementById('siteSidebar').classList.remove('show')"><i class="bi bi-x-lg"></i></button>
        <?php echo \App\Core\View::partial('layouts/partials/nav', ['currentUser' => $currentUser, 'siteName' => $siteName, 'templateCompact' => false]); ?>
        <?php if ($currentUser): ?>
        <a class="nav-link danger mt-2" href="/logout"><i class="bi bi-box-arrow-right"></i>退出登录</a>
        <?php endif; ?>
    </div>

    <main class="main-content">
        <div class="topbar">
            <button class="sidebar-toggle d-md-none" onclick="document.getElementById('siteSidebar').classList.add('show')" aria-label="打开导航"><i class="bi bi-list"></i></button>
            <?php if (!empty($siteAnnouncement)): ?>
            <div class="alert alert-warning alert-banner mb-0 flex-grow-1 ms-2" role="alert">
                <i class="bi bi-megaphone"></i> <?= htmlspecialchars($siteAnnouncement) ?>
            </div>
            <?php endif; ?>
        </div>
        <?= $content ?? '' ?>
    </main>

    <?php echo \App\Core\View::partial('layouts/partials/scripts', ['csrfToken' => $csrfToken]); ?>
</body>
</html>