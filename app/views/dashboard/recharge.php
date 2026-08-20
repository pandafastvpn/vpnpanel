<?php
/** @var array $user */
/** @var string $cardsEnabled */
?>
<div class="topbar">
    <h1><i class="bi bi-wallet2"></i> 余额充值</h1>
    <a href="/dashboard" class="btn btn-outline-secondary btn-sm">返回</a>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">当前余额</h5>
                <div class="text-center my-4">
                    <span class="display-4 fw-bold text-primary">¥<?= number_format($user['balance'], 2) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <?php if ($cardsEnabled === '1'): ?>
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-ticket text-primary"></i> 卡密充值</h5>
                <p class="text-muted">输入您获取的卡密进行充值</p>
                <form onsubmit="return doRecharge(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">充值卡密</label>
                        <input type="text" class="form-control form-control-lg text-center" 
                               name="card_no" required 
                               style="font-family: monospace; letter-spacing: 2px;"
                               placeholder="XXXX-XXXX-XXXX-XXXX">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg" id="rechargeBtn">
                        <i class="bi bi-check-circle"></i> 确认充值
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-info-circle display-4 text-muted"></i>
                <h5 class="mt-3">充值功能暂时关闭</h5>
                <p class="text-muted">请联系管理员进行充值</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function doRecharge(event) {
    event.preventDefault();
    const btn = document.getElementById('rechargeBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 充值中...';
    
    const formData = new FormData(event.target);
    const response = await fetch('/recharge', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || '充值成功');
        setTimeout(() => window.location.reload(), 1500);
    } else {
        showToast(result.message || '充值失败', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> 确认充值';
    }
    return false;
}
</script>
