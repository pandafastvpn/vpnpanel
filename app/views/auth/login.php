<h4 class="mb-3">登录</h4>
<div id="loginAlert"></div>
<form id="loginForm" onsubmit="return handleLogin(event)">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
    <div class="mb-3">
        <label class="form-label">邮箱</label>
        <input type="email" class="form-control" name="email" required autofocus placeholder="请输入邮箱">
    </div>
    <div class="mb-3">
        <label class="form-label">密码</label>
        <input type="password" class="form-control" name="password" required placeholder="请输入密码">
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">登录</button>
    <div class="text-center">
        <a href="/register" class="text-decoration-none">没有账号? 立即注册</a>
    </div>
</form>

<script>
async function handleLogin(event) {
    event.preventDefault();
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.textContent = '登录中...';
    
    const formData = new FormData(document.getElementById('loginForm'));
    const response = await fetch('/login', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast('登录成功');
        setTimeout(() => window.location.href = result.redirect || '/dashboard', 800);
    } else {
        document.getElementById('loginAlert').innerHTML = 
            `<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ${result.message}</div>`;
        btn.disabled = false;
        btn.textContent = '登录';
    }
    return false;
}
</script>
