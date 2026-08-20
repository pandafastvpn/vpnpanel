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
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #818cf8;
            --dark: #1e1b4b;
            --gray: #6b7280;
            --light: #eef2ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 250px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: linear-gradient(160deg, #f5f3ff 0%, #eef2ff 40%, #ffffff 100%);
            background-attachment: fixed;
            margin: 0;
            color: var(--dark);
            min-height: 100vh;
        }
        /* ===== 统一骨架：左侧导航 ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #312e81 0%, #1e1b4b 60%, #0f172a 100%);
            color: #c7d2fe;
            padding: 0;
            z-index: 1000;
            overflow-y: auto;
            transition: transform .3s;
        }
        .sidebar-header {
            padding: 1.4rem 1.25rem;
            display: flex; align-items: center; gap: .6rem;
            font-size: 1.15rem; font-weight: 700; color: #fff;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-header i { color: var(--primary-light); }
        .sidebar .nav-title {
            padding: .8rem 1.25rem .25rem;
            font-size: .68rem; text-transform: uppercase; letter-spacing: .08em;
            color: #6b7280; font-weight: 700;
        }
        .sidebar .nav-link {
            color: #c7d2fe !important;
            padding: .7rem 1.25rem;
            display: flex; align-items: center; gap: .6rem;
            transition: all .2s;
            border-left: 3px solid transparent;
            text-decoration: none;
        }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.06); color: #fff !important; }
        .sidebar .nav-link.active {
            background: linear-gradient(90deg, rgba(99,102,241,.35), rgba(99,102,241,.08));
            border-left-color: var(--primary-light);
            color: #fff !important;
        }
        .sidebar .nav-link.danger { color: #fca5a5 !important; }
        .sidebar .nav-link.danger:hover { color: #fff !important; background: rgba(239,68,68,.25); }
        .sidebar-toggle { display: none; border: 0; background: transparent; color: var(--dark); font-size: 1.4rem; }
        /* ===== 统一骨架：内容区 ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        .topbar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem;
        }
        .topbar h1 { font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--dark); }
        /* 现代渐变视觉 */
        .card {
            border: 0;
            border-radius: 1rem;
            background: rgba(255,255,255,.9);
            box-shadow: 0 10px 40px rgba(79,70,229,.08);
        }
        .card-body { padding: 1.5rem; }
        .card-header { border-radius: 1rem 1rem 0 0 !important; border-bottom: 1px solid #eef2ff; background: #fcfcff !important; }
        .card-header.bg-light { background: #f8f9ff !important; }
        .card-header strong { color: var(--dark); }
        .stat-card { display: flex; align-items: center; gap: 1rem; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: #fff;
            box-shadow: 0 8px 20px rgba(79,70,229,.35);
        }
        .stat-icon.alt { background: linear-gradient(135deg, #059669, #34d399); box-shadow: 0 8px 20px rgba(16,185,129,.35); }
        .stat-value { font-size: 1.45rem; font-weight: 800; margin: 0; color: var(--dark); }
        .stat-label { color: var(--gray); font-size: .84rem; margin: 0; }
        .btn { border-radius: .65rem; font-weight: 600; }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: 0; box-shadow: 0 6px 16px rgba(79,70,229,.35);
        }
        .btn-primary:hover { filter: brightness(1.08); }
        .btn-outline-primary { --bs-btn-color: var(--primary); --bs-btn-border-color: #c7d2fe; --bs-btn-hover-bg: var(--primary); }
        .badge { font-weight: 600; border-radius: 999px; }
        .table { margin: 0; }
        .table th { font-weight: 700; color: var(--gray); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; border-bottom-color: #e5e7eb; }
        .page-link { color: var(--primary); border-radius: 9px; margin: 0 2px; border: 0; }
        .page-item.active .page-link { background: var(--primary); border-color: var(--primary); }
        .alert-banner { border-radius: .9rem; margin-bottom: 1.5rem; }
        .vpn-credential-box {
            background: var(--dark); color: #e0e7ff;
            padding: 1.4rem; border-radius: .9rem;
            font-family: 'Courier New', monospace; position: relative;
        }
        .vpn-credential-box .copy-btn { position: absolute; top: .5rem; right: .5rem; }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #d1d5db; border-radius: 24px; transition: .3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: .3s; }
        input:checked + .toggle-slider { background: var(--success); }
        input:checked + .toggle-slider:before { transform: translateX(20px); }
        .modal-content { border: 0; border-radius: 1rem; }
        .modal-header { border-bottom: 1px solid #eef2ff; background: #f8f9ff; border-radius: 1rem 1rem 0 0; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(79,70,229,.15); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
            .topbar { align-items: flex-start; flex-wrap: wrap; gap: .5rem; }
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
        <button class="sidebar-toggle d-md-none position-absolute top-0 end-0 m-2 text-white" onclick="document.getElementById('siteSidebar').classList.remove('show')"><i class="bi bi-x-lg"></i></button>
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