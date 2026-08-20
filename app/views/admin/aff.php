<?php
/** @var array $stats */
/** @var array $list */
/** @var string $tab */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-megaphone"></i> 推广管理</h1>
</div>

<!-- 统计 -->
<div class="row mb-4">
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">推广人数</small>
            <h4><?= $stats['total_referrers'] ?></h4>
        </div></div>
    </div>
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">总邀请数</small>
            <h4><?= $stats['total_invites'] ?></h4>
        </div></div>
    </div>
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">总佣金</small>
            <h4 class="text-success">¥<?= number_format($stats['total_commission'], 2) ?></h4>
        </div></div>
    </div>
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">待审核佣金</small>
            <h4 class="text-warning">¥<?= number_format($stats['pending_commission'], 2) ?></h4>
        </div></div>
    </div>
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">已通过佣金</small>
            <h4 class="text-info">¥<?= number_format($stats['approved_commission'], 2) ?></h4>
        </div></div>
    </div>
    <div class="col-md-2 mb-2">
        <div class="card"><div class="card-body text-center">
            <small class="text-muted d-block">待处理提现</small>
            <h4 class="text-danger"><?= $stats['pending_withdrawals'] ?></h4>
        </div></div>
    </div>
</div>

<!-- Tab切换 -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'commissions' ? 'active' : '' ?>" href="/admin/aff?tab=commissions">佣金记录</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'withdrawals' ? 'active' : '' ?>" href="/admin/aff?tab=withdrawals">提现管理</a>
    </li>
</ul>

<div class="card">
    <div class="card-body">
        <?php if ($tab === 'commissions'): ?>
        <!-- 佣金记录 -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>推广人</th>
                        <th>被邀请人</th>
                        <th>订单ID</th>
                        <th>订单金额</th>
                        <th>佣金比例</th>
                        <th>佣金</th>
                        <th>状态</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><?= htmlspecialchars($c['referrer_email'] ?? '用户#' . $c['referrer_id']) ?></td>
                        <td><?= htmlspecialchars($c['invited_email'] ?? '用户#' . $c['invited_user_id']) ?></td>
                        <td>#<?= $c['order_id'] ?></td>
                        <td>¥<?= number_format($c['order_amount'], 2) ?></td>
                        <td><?= (float)$c['commission_rate'] ?>%</td>
                        <td class="fw-bold text-success">¥<?= number_format($c['commission'], 2) ?></td>
                        <td>
                            <?php
                            $sMap = [
                                'pending' => '<span class="badge bg-warning">待审核</span>',
                                'approved' => '<span class="badge bg-success">已通过</span>',
                                'locked' => '<span class="badge bg-info">提现中</span>',
                                'withdrawn' => '<span class="badge bg-secondary">已提现</span>',
                            ];
                            echo $sMap[$c['status']] ?? $c['status'];
                            ?>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></small></td>
                        <td>
                            <?php if ($c['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="approveCommission(<?= $c['id'] ?>)">通过</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-3">暂无记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- 提现管理 -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户</th>
                        <th>金额</th>
                        <th>方式</th>
                        <th>收款账号</th>
                        <th>状态</th>
                        <th>申请时间</th>
                        <th>处理时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td><?= htmlspecialchars($w['email'] ?? '用户#' . $w['user_id']) ?></td>
                        <td class="fw-bold">¥<?= number_format($w['amount'], 2) ?></td>
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
                                'pending' => '<span class="badge bg-warning">待处理</span>',
                                'approved' => '<span class="badge bg-success">已通过</span>',
                                'rejected' => '<span class="badge bg-danger">已驳回</span>',
                            ];
                            echo $wMap[$w['status']] ?? $w['status'];
                            ?>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($w['created_at'])) ?></small></td>
                        <td><small><?= $w['processed_at'] ? date('Y-m-d H:i', strtotime($w['processed_at'])) : '-' ?></small></td>
                        <td>
                            <?php if ($w['status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-outline-success" onclick="approveWithdrawal(<?= $w['id'] ?>)">通过</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="rejectWithdrawal(<?= $w['id'] ?>)">驳回</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">暂无记录</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $page - 1 ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=<?= $tab ?>&page=<?= $page + 1 ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
async function approveCommission(id) {
    if (!confirm('确认审核通过此佣金?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('id', id);
    const response = await fetch('/admin/aff/commission/approve', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
    if (result.success) setTimeout(() => location.reload(), 800);
}

async function approveWithdrawal(id) {
    if (!confirm('确认通过此提现申请?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('id', id);
    const response = await fetch('/admin/aff/withdrawal/approve', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
    if (result.success) setTimeout(() => location.reload(), 800);
}

async function rejectWithdrawal(id) {
    if (!confirm('确认驳回此提现申请? 佣金将退回给用户.')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('id', id);
    const response = await fetch('/admin/aff/withdrawal/reject', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
    if (result.success) setTimeout(() => location.reload(), 800);
}
</script>
