<?php
/** @var int $totalUsers */
/** @var int $totalAccounts */
/** @var int $activeAccounts */
/** @var int $expiredAccounts */
/** @var float $totalRevenue */
/** @var int $totalCards */
/** @var int $unusedCards */
/** @var int $usedCards */
/** @var float $todayRevenue */
/** @var int $todayNewUsers */
/** @var int $todayNewAccounts */
/** @var array $orderTrend */
/** @var array $recentOrders */
/** @var array $expiringAccounts */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-speedometer2"></i> 管理后台</h1>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                    <div>
                        <p class="stat-label">总用户数</p>
                        <p class="stat-value"><?= $totalUsers ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <p class="stat-label">活跃VPN账户</p>
                        <p class="stat-value"><?= $activeAccounts ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <p class="stat-label">总收入</p>
                        <p class="stat-value">¥<?= number_format($totalRevenue, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    <div>
                        <p class="stat-label">过期/禁用账户</p>
                        <p class="stat-value"><?= $expiredAccounts ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <p class="text-muted mb-1">今日收入</p>
                <h4 class="text-success">¥<?= number_format($todayRevenue, 2) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <p class="text-muted mb-1">今日新增用户</p>
                <h4 class="text-primary"><?= $todayNewUsers ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <p class="text-muted mb-1">今日新增VPN</p>
                <h4 class="text-info"><?= $todayNewAccounts ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 bg-light">
            <div class="card-body text-center">
                <p class="text-muted mb-1">未使用卡密</p>
                <h4 class="text-warning"><?= $unusedCards ?> / <?= $totalCards ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">最近订单</h5>
                <?php if (empty($recentOrders)): ?>
                <p class="text-muted text-center py-3">暂无订单</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>订单号</th>
                                <th>用户</th>
                                <th>VPN账号</th>
                                <th>套餐</th>
                                <th>金额</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><small class="font-monospace"><?= substr($order['order_no'], -8) ?></small></td>
                                <td><small><?= htmlspecialchars($order['email'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($order['vpn_username'] ?? '-') ?></small></td>
                                <td><small><?= htmlspecialchars($order['package_name']) ?></small></td>
                                <td>¥<?= number_format($order['amount'], 2) ?></td>
                                <td><small><?= date('m-d H:i', strtotime($order['created_at'])) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">即将过期账户</h5>
                <?php if (empty($expiringAccounts)): ?>
                <p class="text-muted text-center py-3">暂无即将过期的账户</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>VPN账号</th>
                                <th>用户</th>
                                <th>到期</th>
                                <th>剩余</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringAccounts as $account): 
                                $daysLeft = ceil((strtotime($account['expire_time']) - time()) / 86400);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($account['username']) ?></td>
                                <td><small><?= htmlspecialchars($account['email'] ?? '-') ?></small></td>
                                <td><small><?= date('m-d H:i', strtotime($account['expire_time'])) ?></small></td>
                                <td>
                                    <span class="badge bg-<?= $daysLeft <= 0 ? 'danger' : ($daysLeft <= 1 ? 'danger' : 'warning') ?>">
                                        <?= $daysLeft <= 0 ? '已过期' : $daysLeft . '天' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
