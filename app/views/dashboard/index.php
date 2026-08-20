<?php
/** @var array|null $vpnAccount */
/** @var array $user */
/** @var array $recentOrders */
/** @var array $packages */
/** @var array|null $currentUser */
?>
<div class="topbar">
    <h1><i class="bi bi-speedometer2"></i> 我的面板</h1>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <p class="stat-label">账户余额</p>
                        <p class="stat-value">¥<?= number_format($user['balance'], 2) ?></p>
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
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <p class="stat-label">VPN状态</p>
                        <p class="stat-value">
                            <?php if ($vpnAccount): ?>
                                <?php if ($vpnAccount['status'] === 'enabled' && strtotime($vpnAccount['expire_time']) > time()): ?>
                                    <span class="badge bg-success">运行中</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">已停用</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary">未开通</span>
                            <?php endif; ?>
                        </p>
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
                        <i class="bi bi-clock"></i>
                    </div>
                    <div>
                        <p class="stat-label">到期时间</p>
                        <p class="stat-value fs-6">
                            <?php if ($vpnAccount): ?>
                                <?= date('Y-m-d', strtotime($vpnAccount['expire_time'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </p>
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
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <div>
                        <p class="stat-label">已用流量</p>
                        <p class="stat-value fs-6">
                            <?php if ($vpnAccount): ?>
                                <?= formatBytes($vpnAccount['data_used_bytes']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$vpnAccount): ?>
<div class="card border-primary mb-4">
    <div class="card-body text-center py-4">
        <h4 class="text-primary"><i class="bi bi-rocket-takeoff"></i> 立即开通VPN服务</h4>
        <p class="text-muted">选择套餐, 自动开通, 即买即用</p>
        <a href="/packages" class="btn btn-primary btn-lg">查看套餐</a>
    </div>
</div>
<?php else: ?>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-shield-lock text-primary"></i> VPN账户信息</h5>
                <div class="border rounded p-3 mb-3 position-relative" style="background: #f8fafc;">
                    <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" onclick="copyToClipboard('<?= htmlspecialchars($vpnAccount['username']) ?>')" title="复制账号">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <div class="mb-2">
                        <small class="text-muted fw-bold">账号</small><br>
                        <span style="font-size: 1.2rem; font-weight: bold; letter-spacing: 1px; color: #1e293b; font-family: monospace;"><?= htmlspecialchars($vpnAccount['username']) ?></span>
                    </div>
                    <hr class="my-2">
                    <button class="btn btn-sm btn-outline-secondary position-absolute end-0 m-2" style="top: 3.25rem;" onclick="copyToClipboard('<?= htmlspecialchars($vpnAccount['password']) ?>')" title="复制密码">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <div>
                        <small class="text-muted fw-bold">密码</small><br>
                        <span style="font-size: 1.2rem; font-weight: bold; color: #1e293b; font-family: monospace; word-break: break-all;"><?= htmlspecialchars($vpnAccount['password']) ?></span>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-4">
                        <small class="text-muted d-block">下载速率</small>
                        <strong><?= round($vpnAccount['down_rate'] / 1024, 1) ?> Mbps</strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">并发连接</small>
                        <strong><?= $vpnAccount['active_num'] ?></strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">服务器</small>
                        <strong>
                            <?php if (isset($vpnNodes) && is_array($vpnNodes) && !empty($vpnNodes)): ?>
                                <?= htmlspecialchars($vpnNodes[0]['host'] . ':' . ($vpnNodes[0]['port'] ?? 443)) ?>
                            <?php elseif (defined('OCSERV_HOST') && OCSERV_HOST !== 'your_vpn_server_ip'): ?>
                                <?= htmlspecialchars(OCSERV_HOST . ':' . (OCSERV_PORT ?? 443)) ?>
                            <?php else: ?>
                                未配置
                            <?php endif; ?>
                        </strong>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/vpn-account" class="btn btn-outline-primary btn-sm w-100">管理VPN账户</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0"><i class="bi bi-clock-history text-primary"></i> 最近订单</h5>
                    <a href="/orders" class="btn btn-sm btn-outline-primary">全部</a>
                </div>
                <?php if (empty($recentOrders)): ?>
                <p class="text-muted text-center py-3">暂无订单记录</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>订单号</th>
                                <th>套餐</th>
                                <th>金额</th>
                                <th>状态</th>
                                <th>日期</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><small><?= substr($order['order_no'], -8) ?></small></td>
                                <td><?= htmlspecialchars($order['package_name']) ?></td>
                                <td>¥<?= number_format($order['amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusMap = ['pending' => '<span class="badge bg-warning">待支付</span>', 'paid' => '<span class="badge bg-success">已支付</span>', 'cancelled' => '<span class="badge bg-secondary">已取消</span>'];
                                    echo $statusMap[$order['status']] ?? $order['status'];
                                    ?>
                                </td>
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
</div>
<?php endif; ?>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
