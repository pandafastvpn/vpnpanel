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
            --bg: #0b1020;
            --bg-soft: #0f1527;
            --panel: #131a2e;
            --border: rgba(148,163,184,.14);
            --text: #e2e8f0;
            --muted: #8ea0bd;
            --accent: #22d3ee;
            --accent-2: #818cf8;
            --danger: #f87171;
            --success: #34d399;
            --sidebar-width: 250px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: var(--bg);
            background-image: radial-gradient(circle at 90% -20%, rgba(34,211,238,.10), transparent 45%),
                              radial-gradient(circle at 5% 110%, rgba(129,140,248,.08), transparent 40%);
            background-attachment: fixed;
            margin: 0;
            color: var(--text);
            min-height: 100vh;
        }
        /* ===== 统一骨架：左侧导航 ===== */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background: rgba(15,21,39,.88);
            backdrop-filter: blur(12px);
            border-right: 1px solid var(--border);
            z-index: 1000;
            overflow-y: auto;
            padding: 1rem .8rem;
            transition: transform .3s;
        }
        .sidebar-header {
            display: flex; align-items: center; gap: .6rem;
            padding: .4rem .5rem 1rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: .8rem;
        }
        .sidebar-header i { color: var(--accent); font-size: 1.2rem; }
        .sidebar-header { font-weight: 700; color: var(--text); font-size: 1.05rem; }
        .sidebar .nav-title {
            font-size: .68rem; text-transform: uppercase; letter-spacing: .12em;
            color: #54638a; margin: 1rem .5rem .35rem; font-weight: 700;
        }
        .sidebar .nav-link {
            display: flex; align-items: center; gap: .6rem;
            padding: .58rem .7rem; border-radius: 10px;
            color: var(--muted); text-decoration: none;
            font-size: .92rem; margin-bottom: 2px;
            transition: all .15s;
        }
        .sidebar .nav-link i { font-size: 1.02rem; width: 1.2rem; text-align: center; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.05); color: var(--text); }
        .sidebar .nav-link.active { background: linear-gradient(90deg, rgba(34,211,238,.16), rgba(129,140,248,.10)); color: var(--accent); border-left: 3px solid var(--accent); }
        .sidebar .nav-link.danger { color: var(--danger); }
        .sidebar-toggle { display: none; border: 0; background: transparent; color: var(--text); font-size: 1.4rem; }
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
        .topbar h1 { font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text); }
        /* 暗色视觉 */
        .card {
            background: linear-gradient(180deg, var(--panel), var(--bg-soft));
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,.35);
        }
        .card-header {
            background: rgba(255,255,255,.028);
            border-bottom: 1px solid var(--border);
            border-radius: 14px 14px 0 0 !important;
        }
        .card-header.bg-light { background: rgba(255,255,255,.04) !important; }
        .card-header strong { color: var(--text); }
        .stat-card { display: flex; align-items: center; gap: 1rem; }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
            background: linear-gradient(135deg, #0e7490, #0891b2); color: #cffafe;
            box-shadow: 0 0 16px rgba(34,211,238,.25);
        }
        .stat-icon.alt { background: linear-gradient(135deg, #4f46e5, #6366f1); color: #e0e7ff; box-shadow: 0 0 16px rgba(99,102,241,.25); }
        .stat-value { font-size: 1.45rem; font-weight: 800; margin: 0; color: var(--text); }
        .stat-label { color: var(--muted); font-size: .84rem; margin: 0; }
        .btn { border-radius: 10px; }
        .btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent-2)); border: 0; color: #0b1020; font-weight: 600; }
        .btn-primary:hover { filter: brightness(1.12); color: #0b1020; }
        .btn-outline-primary { color: var(--accent); border-color: rgba(34,211,238,.4); --bs-btn-hover-bg: rgba(34,211,238,.15); --bs-btn-hover-color: var(--accent); }
        .btn-outline-danger { color: var(--danger); border-color: rgba(248,113,113,.4); --bs-btn-hover-bg: rgba(248,113,113,.15); --bs-btn-hover-color: var(--danger); }
        .badge { font-weight: 600; border-radius: 999px; }
        .badge.bg-success { background: rgba(52,211,153,.18) !important; color: var(--success); }
        .badge.bg-secondary { background: rgba(148,163,184,.18) !important; color: var(--muted); }
        .badge.bg-info { background: rgba(34,211,238,.18) !important; color: var(--accent); }
        .badge.bg-danger { background: rgba(248,113,113,.18) !important; color: var(--danger); }
        .badge.bg-warning { background: rgba(251,191,36,.18) !important; color: #fcd34d; }
        .badge.bg-primary { background: rgba(129,140,248,.2) !important; color: var(--accent-2); }
        .table { --bs-table-bg: transparent; color: var(--text); margin: 0; }
        .table thead th { color: var(--muted); font-size: .76rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; border-bottom-color: var(--border); }
        .table td { border-color: rgba(148,163,184,.08); color: var(--text); }
        .table-hover tbody tr:hover { background: rgba(255,255,255,.03); }
        .text-muted { color: var(--muted) !important; }
        .page-link { background: var(--panel); color: var(--accent); border: 0; border-radius: 9px; margin: 0 2px; }
        .page-item.active .page-link { background: var(--accent); color: #0b1020; }
        .alert-banner { background: rgba(251,191,36,.10); border: 1px solid rgba(251,191,36,.25); color: #fde68a; border-radius: 12px; }
        .vpn-credential-box {
            background: #0b1222; border: 1px solid var(--border);
            color: var(--text); padding: 1.4rem; border-radius: 12px;
            font-family: 'Courier New', monospace; position: relative;
        }
        .vpn-credential-box .copy-btn { position: absolute; top: .5rem; right: .5rem; }
        .modal-content { background: var(--panel); border: 1px solid var(--border); border-radius: 14px; color: var(--text); }
        .modal-header { border-bottom-color: var(--border); }
        .modal-footer { border-top-color: var(--border); }
        .modal-title { color: var(--text); }
        .form-label { color: var(--text); }
        .form-control, .form-select {
            background: var(--bg-soft); border: 1px solid var(--border);
            color: var(--text); border-radius: 10px;
        }
        .form-control:focus, .form-select:focus { background: var(--bg-soft); color: var(--text); border-color: var(--accent); box-shadow: 0 0 0 .2rem rgba(34,211,238,.15); }
        .form-control::placeholder { color: #526079; }
        .form-text { color: var(--muted); }
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #334155; border-radius: 24px; transition: .3s; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: #e2e8f0; border-radius: 50%; transition: .3s; }
        input:checked + .toggle-slider { background: var(--success); }
        input:checked + .toggle-slider:before { transform: translateX(20px); }
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