<?php
/** @var array $packages */
/** @var string $announcement */
/** @var string $notice */
/** @var array|null $currentUser */
?>
<div class="topbar">
    <h1><i class="bi bi-shop"></i> VPN商店</h1>
    <?php if (!$currentUser): ?>
        <div>
            <a href="/login" class="btn btn-outline-primary btn-sm me-2">登录</a>
            <a href="/register" class="btn btn-primary btn-sm">注册</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($notice): ?>
<div class="alert alert-info" role="alert">
    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($notice) ?>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h3 class="mb-3"><i class="bi bi-shield-lock text-primary"></i> 安全高速VPN服务</h3>
                <p class="text-muted mb-3">基于Cisco AnyConnect协议(ocserv), 企业级安全加密, 全球节点加速, 稳定可靠。</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 企业级加密 - Cisco AnyConnect协议</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 高速带宽 - 最高20Mbps</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 多设备支持 - 最多5个并发连接</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> 即买即用 - 自动开通, 无需等待</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h2 class="mb-2"><i class="bi bi-lightning-charge"></i></h2>
                <h4>立即开通</h4>
                <p class="mb-3">注册即享VPN服务</p>
                <?php if ($currentUser): ?>
                    <a href="/packages" class="btn btn-light btn-lg">查看套餐</a>
                <?php else: ?>
                    <a href="/register" class="btn btn-light btn-lg">免费注册</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h3 class="mb-3">套餐价格</h3>
<div class="row">
    <?php foreach ($packages as $i => $pkg): ?>
    <div class="col-md-4 mb-3">
        <div class="card h-100 <?= $i === 1 ? 'border-primary' : '' ?>">
            <?php if ($i === 1): ?>
            <div class="card-header bg-primary text-white text-center py-1">
                <small><i class="bi bi-star"></i> 热门</small>
            </div>
            <?php endif; ?>
            <div class="card-body text-center">
                <h5 class="card-title"><?= htmlspecialchars($pkg['name']) ?></h5>
                <p class="text-muted small"><?= htmlspecialchars($pkg['description']) ?></p>
                <?php $pricing = $pkg['default_pricing'] ?? null; ?>
                <div class="my-3">
                    <?php if ($pricing): ?>
                    <span class="display-6 fw-bold text-primary">¥<?= number_format($pricing['price'], 2) ?></span>
                    <?php else: ?>
                    <span class="display-6 fw-bold text-danger">暂无定价</span>
                    <?php endif; ?>
                </div>
                <ul class="list-unstyled text-start mb-3">
                    <?php if ($pricing): ?>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> 有效期 <?= $pricing['duration_days'] ?> 天</li>
                    <?php else: ?>
                    <li class="mb-1"><i class="bi bi-x text-danger"></i> 暂无可用定价方案</li>
                    <?php endif; ?>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> 下载 <?= round($pkg['down_rate'] / 1024, 1) ?> Mbps</li>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> 上传 <?= round($pkg['up_rate'] / 1024, 1) ?> Mbps</li>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $pkg['active_num'] ?> 并发连接</li>
                    <?php if ($pkg['data_limit'] > 0): ?>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> <?= $pkg['data_limit'] ?>GB 流量</li>
                    <?php else: ?>
                    <li class="mb-1"><i class="bi bi-check text-success"></i> 不限流量</li>
                    <?php endif; ?>
                </ul>
                <?php if ($pricing): ?>
                <a href="/checkout/<?= $pkg['id'] ?>" class="btn btn-primary w-100 mb-2">立即购买</a>
                <?php else: ?>
                <a href="/packages" class="btn btn-outline-primary w-100 mb-2">查看套餐</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
async function buyPackage(event, packageId, pricingId = 0) {
    event.preventDefault();
    if (!confirm('确认购买此套餐? 将从余额中扣除费用。')) return false;
    
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('pricing_id', pricingId || 0);
    
    const response = await fetch('/buy/' + packageId, { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || '购买成功!');
        setTimeout(() => window.location.href = result.redirect || '/dashboard', 1500);
    } else {
        showToast(result.message || '购买失败', 'error');
        if (result.redirect) {
            setTimeout(() => window.location.href = result.redirect, 1500);
        }
    }
    return false;
}
</script>
