<h4 class="mb-3">注册</h4>
<div id="registerAlert"></div>
<form id="registerForm" onsubmit="return handleRegister(event)">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
    <div class="mb-3">
        <label class="form-label">邮箱</label>
        <input type="email" class="form-control" name="email" required autofocus placeholder="请输入邮箱">
    </div>
    <div class="mb-3">
        <label class="form-label">手机号 <small class="text-muted">(选填)</small></label>
        <input type="text" class="form-control" name="phone" placeholder="选填">
    </div>
    <div class="mb-3">
        <label class="form-label">密码</label>
        <input type="password" class="form-control" name="password" required placeholder="至少6位">
    </div>
    <div class="mb-3">
        <label class="form-label">确认密码</label>
        <input type="password" class="form-control" name="confirm_password" required placeholder="再次输入密码">
    </div>
    <div class="mb-3" id="refField" style="display:none;">
        <label class="form-label">推荐码 <small class="text-muted">(来自朋友推荐)</small></label>
        <input type="text" class="form-control" name="ref_code" value="" readonly>
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3" id="registerBtn">注册</button>
    <div class="text-center">
        <a href="/login" class="text-decoration-none">已有账号? 去登录</a>
    </div>
</form>

<script>
// 从URL获取推荐码
const urlParams = new URLSearchParams(window.location.search);
const refCode = urlParams.get('ref');
if (refCode) {
    const refInput = document.querySelector('input[name="ref_code"]');
    refInput.value = refCode;
    document.getElementById('refField').style.display = '';
}

async function handleRegister(event) {
    event.preventDefault();
    const btn = document.getElementById('registerBtn');
    btn.disabled = true;
    btn.textContent = '注册中...';
    
    const formData = new FormData(document.getElementById('registerForm'));
    const response = await fetch('/register', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || '注册成功');
        setTimeout(() => window.location.href = '/login', 1500);
    } else {
        document.getElementById('registerAlert').innerHTML = 
            `<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> ${result.message}</div>`;
        btn.disabled = false;
        btn.textContent = '注册';
    }
    return false;
}
</script>
