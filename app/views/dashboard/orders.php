<?php
/** @var array $orders */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="topbar">
    <h1><i class="bi bi-clock-history"></i> 订单记录</h1>
    <a href="/dashboard" class="btn btn-outline-secondary btn-sm">返回</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-receipt display-4 text-muted"></i>
            <h5 class="mt-3 text-muted">暂无订单记录</h5>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>订单号</th>
                        <th>套餐</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>支付时间</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><small class="font-monospace"><?= htmlspecialchars($order['order_no']) ?></small></td>
                        <td><?= htmlspecialchars($order['package_name']) ?></td>
                        <td>¥<?= number_format($order['amount'], 2) ?></td>
                        <td>
                            <?php
                            $statusMap = [
                                'pending' => '<span class="badge bg-warning">待支付</span>',
                                'paid' => '<span class="badge bg-success">已支付</span>',
                                'cancelled' => '<span class="badge bg-secondary">已取消</span>',
                                'expired' => '<span class="badge bg-danger">已过期</span>',
                            ];
                            echo $statusMap[$order['status']] ?? $order['status'];
                            ?>
                        </td>
                        <td><?= $order['paid_at'] ? date('Y-m-d H:i', strtotime($order['paid_at'])) : '-' ?></td>
                        <td><small><?= date('Y-m-d H:i', strtotime($order['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
