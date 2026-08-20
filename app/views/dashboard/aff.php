<?php
/** @var array $stats */
/** @var array $invites */
/** @var array $commissions */
/** @var array $withdrawals */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-megaphone"></i> 推广赚钱</h1>
</div>

<!-- 统计卡片 -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <p class="stat-label">已邀请用户</p>
                        <p class="stat-value"><?= $stats['total_invites'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <p class="stat-label">累计佣金</p>
                        <p class="stat-value">¥<?= number_format($stats['total_commission'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div>
                        <p class="stat-label">待审核佣金</p>
                        <p class="stat-value">¥<?= number_format($stats['pending_commission'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <p class="stat-label">可提现佣金</p>
                        <p class="stat-value">¥<?= number_format($stats['available_commission'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 推荐链接 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-link-45deg text-primary"></i> 我的推荐链接</h5>
        <div class="input-group input-group-lg">
            <input type="text" class="form-control font-monospace" id="refLink" value="<?= htmlspecialchars($stats['ref_link']) ?>" readonly>
            <button class="btn btn-primary" onclick="copyToClipboard(document.getElementById('refLink').value)">
                <i class="bi bi-clipboard"></i> 复制
            </button>
        </div>
        <div class="mt-2">
            <small class="text-muted">推荐码: <code class="fw-bold text-primary"><?= htmlspecialchars($stats['ref_code']) ?></code></small>
        </div>
        <div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle"></i> 
            分享你的推荐链接给朋友, 他们注册并购买套餐后, 你将获得订单金额的佣金奖励。
        </div>
    </div>
</div>

<!-- 提现申请 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-cash-stack text-primary"></i> 申请提现</h5>
        <form onsubmit="return submitWithdraw(event)">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small text-muted">提现金额</label>
                    <input type="number" class="form-control" name="amount" step="0.01" min="1" placeholder="¥" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">提现方式</label>
                    <select class="form-select" name="method" required>
                        <option value="">选择方式</option>
                        <option value="alipay">支付宝</option>
                        <option value="wechat">微信</option>
                        <option value="bank">银行卡</option>
                        <option value="usdt">USDT</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">收款账号</label>
                    <input type="text" class="form-control" name="account" placeholder="填写收款账号/地址" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">提交</button>
                </div>
            </div>
            <small class="text-muted d-block mt-2">可提现: ¥<?= number_format($stats['available_commission'], 2) ?> | 已提现: ¥<?= number_format($stats['withdrawn_commission'], 2) ?></small>
        </form>
    </div>
</div>

<!-- 邀请记录 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-people text-primary"></i> 邀请记录</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>用户</th><th>推荐码</th><th>状态</th><th>注册时间</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($invites as $inv): ?>
                    <tr>
                        <td><?= htmlspecialchars($inv['invited_email'] ?? '用户#' . $inv['invited_user_id']) ?></td>
                        <td><code><?= htmlspecialchars($inv['ref_code']) ?></code></td>
                        <td>
                            <?php
                            $map = ['registered' => '<span class="badge bg-info">已注册</span>', 'ordered' => '<span class="badge bg-success">已下单</span>'];
                            echo $map[$inv['status']] ?? $inv['status'];
                            ?>
                        </td>
                        <td><small><?= $inv['registered_at'] ? date('Y-m-d H:i', strtotime($inv['registered_at'])) : '-' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invites)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">暂无邀请记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 佣金记录 -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-coin text-primary"></i> 佣金记录</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>订单ID</th><th>被邀请用户</th><th>订单金额</th><th>佣金比例</th><th>佣金</th><th>状态</th><th>时间</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $c): ?>
                    <tr>
                        <td>#<?= $c['order_id'] ?></td>
                        <td><?= htmlspecialchars($c['invited_email'] ?? '-') ?></td>
                        <td>¥<?= number_format($c['order_amount'], 2) ?></td>
                        <td><?= (float)$c['commission_rate'] ?>%</td>
                        <td class="fw-bold text-success">¥<?= number_format($c['commission'], 2) ?></td>
                        <td>
                            <?php
                            $sMap = [
                                'pending' => '<span class="badge bg-warning">待审核</span>',
                                'approved' => '<span class="badge bg-success">可提现</span>',
                                'locked' => '<span class="badge bg-info">提现中</span>',
                                'withdrawn' => '<span class="badge bg-secondary">已提现</span>',
                            ];
                            echo $sMap[$c['status']] ?? $c['status'];
                            ?>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($commissions)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">暂无佣金记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 提现记录 -->
<div class="card">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-arrow-down-up text-primary"></i> 提现记录</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>金额</th><th>方式</th><th>账号</th><th>状态</th><th>申请时间</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $w): ?>
                    <tr>
                        <td>¥<?= number_format($w['amount'], 2) ?></td>
                        <td>
                            <?php
                            $mMap = ['alipay' => '支付宝', 'wechat' => '微信', 'bank' => '银行卡', 'usdt' => 'USDT'];
                            echo $mMap[$w['method']] ?? $w['method'];
                            ?>
                        </td>
                        <td><code class="small"><?= htmlspecialchars($w['account']) ?></code></td>
                        <td>
                            <?php
                            $wMap = [
                                'pending' => '<span class="badge bg-warning">审核中</span>',
                                'approved' => '<span class="badge bg-success">已通过</span>',
                                'rejected' => '<span class="badge bg-danger">已驳回</span>',
                            ];
                            echo $wMap[$w['status']] ?? $w['status'];
                            ?>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($w['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($withdrawals)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">暂无提现记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function submitWithdraw(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const response = await fetch('/aff/withdraw', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '提交成功' : '提交失败'), result.success ? 'success' : 'error');
    if (result.success) {
        setTimeout(() => location.reload(), 1200);
    }
    return false;
}
</script>
