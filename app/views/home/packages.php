<?php
/** @var array $packages */
/** @var array|null $vpnAccount */
/** @var array $gateways */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-box-seam"></i> 选择套餐</h1>
    <a href="/dashboard" class="btn btn-outline-secondary btn-sm">返回</a>
</div>

<div class="row">
    <?php foreach ($packages as $i => $pkg): ?>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-1"><?= htmlspecialchars($pkg['name']) ?></h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($pkg['description']) ?></p>
                    </div>
                    <?php if ($pkg['data_limit'] > 0): ?>
                    <span class="badge bg-info"><?= $pkg['data_limit'] ?>GB</span>
                    <?php else: ?>
                    <span class="badge bg-success">不限流量</span>
                    <?php endif; ?>
                </div>

                <div class="row text-center mb-3 mt-3">
                    <div class="col-4 border-end">
                        <small class="text-muted d-block">下载速率</small>
                        <strong><?= round($pkg['down_rate'] / 1024, 1) ?> Mbps</strong>
                    </div>
                    <div class="col-4 border-end">
                        <small class="text-muted d-block">上传速率</small>
                        <strong><?= round($pkg['up_rate'] / 1024, 1) ?> Mbps</strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">并发连接</small>
                        <strong><?= $pkg['active_num'] ?></strong>
                    </div>
                </div>

                <?php if (!empty($pkg['pricings'])): ?>
                <div class="mb-3">
                    <?php
                    $lowest = null;
                    foreach ($pkg['pricings'] as $pricing) {
                        if ($lowest === null || $pricing['price'] < $lowest) {
                            $lowest = $pricing['price'];
                        }
                    }
                    ?>
                    <div class="text-center mb-2">
                        <small class="text-muted">起价</small>
                        <span class="fs-4 fw-bold text-primary">¥<?= number_format($lowest, 2) ?></span>
                        <small class="text-muted">/ <?= getCycleName($pkg['pricings'][0]['billing_cycle']) ?></small>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">该套餐暂无可用定价方案, 请联系管理员。</div>
                <?php endif; ?>

                <a href="/checkout/<?= $pkg['id'] ?>" class="btn btn-primary w-100">
                    <i class="bi bi-cart-plus"></i> 立即购买
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
function getCycleName($cycle) {
    $map = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付', 'weekly' => '周付', 'biannually' => '半年付'];
    return $map[$cycle] ?? $cycle;
}
?>

