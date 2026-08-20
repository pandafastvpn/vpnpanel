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
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #1e293b;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .auth-card {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            padding: 2.5rem;
            width: 100%;
            max-width: 420px;
        }
        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .auth-logo i {
            font-size: 3rem;
            color: var(--primary);
        }
        .auth-logo h2 {
            font-weight: 700;
            color: var(--dark);
            margin-top: 0.5rem;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99,102,241,0.15);
        }
        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 600;
        }
        .btn-primary:hover { background: var(--primary-dark); }
        .alert { border-radius: 0.5rem; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <i class="bi bi-shield-lock"></i>
            <h2><?= htmlspecialchars($siteName ?? 'VPN商店') ?></h2>
        </div>
        <?= $content ?? '' ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function apiPost(url, data) {
            const formData = new FormData();
            for (const key in data) { formData.append(key, data[key]); }
            formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?? "" ?>');
            const response = await fetch(url, { method: 'POST', body: formData });
            return await response.json();
        }
        function showToast(msg, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 p-3 rounded shadow';
            toast.style.cssText = `background:${type==='success'?'#10b981':'#ef4444'};color:#fff;z-index:9999;min-width:200px;`;
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>
