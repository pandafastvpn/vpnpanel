<?php
/** @var array $user */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-person"></i> 个人设置</h1>
    <a href="/dashboard" class="btn btn-outline-secondary btn-sm">返回</a>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-person-lines text-primary"></i> 基本资料</h5>
                <form onsubmit="return updateProfile(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">邮箱</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small class="text-muted">邮箱不可修改</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">手机号</label>
                        <input type="text" class="form-control" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               placeholder="请输入手机号">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">余额</label>
                        <input type="text" class="form-control" value="¥<?= number_format($user['balance'], 2) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">保存</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-key text-primary"></i> 修改密码</h5>
                <form onsubmit="return changePassword(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">原密码</label>
                        <input type="password" class="form-control" name="old_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">新密码</label>
                        <input type="password" class="form-control" name="new_password" required placeholder="至少6位">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">确认新密码</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">修改密码</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
async function updateProfile(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const response = await fetch('/profile/update', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        showToast('资料更新成功');
    } else {
        showToast(result.message || '更新失败', 'error');
    }
    return false;
}

async function changePassword(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const response = await fetch('/profile/change-password', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        showToast('密码修改成功');
        event.target.reset();
    } else {
        showToast(result.message || '修改失败', 'error');
    }
    return false;
}
</script>
