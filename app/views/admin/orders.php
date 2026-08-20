<?php
/** @var array $orders */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $search */
/** @var string $status */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-receipt"></i> 订单管理</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索订单号/邮箱/VPN账号">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="status">
                    <option value="">全部状态</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>待支付</option>
                    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>已支付</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>已取消</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/orders" class="btn btn-outline-secondary w-100">重置</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>订单号</th>
                        <th>用户</th>
                        <th>VPN账号</th>
                        <th>套餐</th>
                        <th>金额</th>
                        <th>天数</th>
                        <th>速率</th>
                        <th>并发</th>
                        <th>状态</th>
                        <th>支付时间</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><small class="font-monospace"><?= htmlspecialchars($order['order_no']) ?></small></td>
                        <td><small><?= htmlspecialchars($order['email'] ?? '-') ?></small></td>
                        <td><?= htmlspecialchars($order['vpn_username'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($order['package_name']) ?></td>
                        <td>¥<?= number_format($order['amount'], 2) ?></td>
                        <td><?= $order['duration_days'] ?>天</td>
                        <td><small><?= round($order['down_rate'] / 1024, 1) ?>M</small></td>
                        <td><?= $order['active_num'] ?></td>
                        <td>
                            <?php
                            $statusMap = ['pending' => '<span class="badge bg-warning">待支付</span>', 'paid' => '<span class="badge bg-success">已支付</span>', 'cancelled' => '<span class="badge bg-secondary">已取消</span>'];
                            echo $statusMap[$order['status']] ?? $order['status'];
                            ?>
                        </td>
                        <td><small><?= $order['paid_at'] ? date('Y-m-d H:i', strtotime($order['paid_at'])) : '-' ?></small></td>
                        <td><small><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-3">暂无订单</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
