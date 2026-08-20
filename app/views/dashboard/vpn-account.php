<?php
/** @var array $accounts 用户的所有VPN账号 */
/** @var array $sessions 在线会话列表 */
/** @var array $packages 可购买套餐 */
/** @var array $accountSubscriptions 每个账号的订阅 [account_id => ['active'=>sub|null, 'others'=>[...]]] */
/** @var string $csrfToken */

$displayNodes = [];
if (isset($vpnNodes) && is_array($vpnNodes) && !empty($vpnNodes)) {
    $displayNodes = $vpnNodes;
} elseif (defined('OCSERV_HOST') && OCSERV_HOST !== 'your_vpn_server_ip') {
    $displayNodes[] = ['label' => '默认节点', 'host' => OCSERV_HOST, 'port' => OCSERV_PORT ?? 443, 'proto' => OCSERV_PROTO ?? 'anyconnect'];
}

// 辅助函数
if (!function_exists('formatTrafficBytes')) {
    function formatTrafficBytes($bytes) {
        if (!$bytes || $bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes) / log(1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
if (!function_exists('formatDuration')) {
    function formatDuration($seconds) {
        $seconds = (int) $seconds;
        if ($seconds <= 0) return '-';
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        if ($h > 0) return $h . '小时' . $m . '分';
        if ($m > 0) return $m . '分' . $s . '秒';
        return $s . '秒';
    }
}
?>

<?php if (empty($accounts)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-shield-lock" style="font-size: 3rem; color: #cbd5e1;"></i>
        <h4 class="mt-3">您还没有VPN账号</h4>
        <p class="text-muted">购买套餐后将自动创建VPN账号</p>
        <a href="/packages" class="btn btn-primary btn-lg">
            <i class="bi bi-box-seam"></i> 浏览套餐
        </a>
    </div>
</div>
<?php else: ?>

<?php
// 准备节点列表 (从配置文件 $vpnNodes 或旧版常量)
$nodes = [];
if (isset($vpnNodes) && is_array($vpnNodes) && !empty($vpnNodes)) {
    $nodes = $vpnNodes;
} elseif (defined('OCSERV_HOST') && OCSERV_HOST !== 'your_vpn_server_ip') {
    $nodes = [
        ['label' => '默认节点', 'host' => OCSERV_HOST, 'port' => OCSERV_PORT, 'proto' => OCSERV_PROTO],
    ];
}
?>

<!-- 连接信息 (所有账号共用, 放在最前面) -->
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-hdd-network text-primary"></i> 连接信息 <small class="text-muted fw-normal">(所有账号共用)</small></h5>
        <?php if (!empty($nodes)): ?>
        <?php if (count($nodes) === 1): ?>
        <!-- 单节点: 紧凑布局 -->
        <div class="row">
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <small class="text-muted d-block">服务器地址</small>
                    <strong style="font-size: 1.1rem;"><?= htmlspecialchars($nodes[0]['host']) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <small class="text-muted d-block">端口</small>
                    <strong style="font-size: 1.1rem;"><?= $nodes[0]['port'] ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <small class="text-muted d-block">协议</small>
                    <strong style="font-size: 1.1rem;"><?= strtoupper($nodes[0]['proto']) ?></strong>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- 多节点: 表格展示 -->
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                    <tr>
                        <th>节点</th>
                        <th>服务器地址</th>
                        <th>端口</th>
                        <th>协议</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($node['label']) ?></strong></td>
                        <td><code style="font-size: 1rem;"><?= htmlspecialchars($node['host']) ?></code></td>
                        <td><?= $node['port'] ?></td>
                        <td><span class="badge bg-secondary"><?= strtoupper($node['proto']) ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copyToClipboard('<?= htmlspecialchars($node['host'] . ':' . $node['port']) ?>')" title="复制地址">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="mt-3">
            <div class="alert alert-info mb-0">
                <i class="bi bi-info-circle"></i>
                <strong>客户端下载:</strong>
                <a href="https://www.openconnect-vpn.com/download/" target="_blank" class="alert-link">OpenConnect 客户端</a> |
                <a href="https://www.cisco.com/c/en/us/support/security/anyconnect-secure-mobility-client/" target="_blank" class="alert-link">Cisco AnyConnect</a>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            VPN服务器节点尚未配置, 请联系管理员在 `config/config.php` 中填写 `$vpnNodes` 或 `OCSERV_HOST`。
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 多账号列表 -->
<?php foreach ($accounts as $idx => $account): 
    $subs = $accountSubscriptions[$account['id']] ?? ['active' => null, 'others' => []];
    $activeSub = $subs['active'];
    $otherSubs = $subs['others'];
?>
<div class="card mb-4" id="account-<?= $account['id'] ?>">
    <div class="card-header d-flex justify-content-between align-items-center <?= $idx === 0 ? 'bg-primary text-white' : 'bg-light' ?>">
        <h5 class="mb-0">
            <i class="bi bi-shield-lock"></i>
            VPN账号: <strong><?= htmlspecialchars($account['username']) ?></strong>
            <?php if (!empty($account['remark'])): ?>
            <span class="badge <?= $idx === 0 ? 'bg-light text-primary' : 'bg-secondary' ?> ms-2"><?= htmlspecialchars($account['remark']) ?></span>
            <?php endif; ?>
        </h5>
        <div>
            <?php if ($account['status'] === 'enabled'): ?>
                <span class="badge bg-success"><i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> 运行中</span>
            <?php elseif ($account['status'] === 'traffic_exceeded'): ?>
                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle"></i> 流量超限</span>
            <?php elseif ($account['status'] === 'disabled' && strtotime($account['expire_time']) < time()): ?>
                <span class="badge bg-danger">已过期</span>
            <?php else: ?>
                <span class="badge bg-secondary">已禁用</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- 凭据 -->
            <div class="col-md-4">
                <div class="border rounded p-3 mb-2 position-relative" style="background: #f8fafc;">
                    <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" onclick="copyToClipboard('<?= htmlspecialchars($account['username']) ?>')" title="复制账号">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <div class="mb-2">
                        <small class="text-muted fw-bold">VPN账号</small>
                        <div style="font-size: 1.3rem; font-weight: bold; letter-spacing: 2px; color: #1e293b; font-family: monospace;"><?= htmlspecialchars($account['username']) ?></div>
                    </div>
                    <hr class="my-2">
                    <div>
                        <button class="btn btn-sm btn-outline-secondary position-absolute end-0 m-2" style="bottom: 8px;" onclick="copyToClipboard('<?= htmlspecialchars($account['password']) ?>')" title="复制密码">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <small class="text-muted fw-bold">VPN密码</small>
                        <div style="font-size: 1.3rem; font-weight: bold; color: #1e293b; font-family: monospace; word-break: break-all;"><?= htmlspecialchars($account['password']) ?></div>
                    </div>
                </div>
                <button class="btn btn-outline-warning btn-sm w-100 mb-1" onclick="showPasswordModal(<?= $account['id'] ?>)">
                    <i class="bi bi-key"></i> 修改密码
                </button>
                <?php if ($account['data_limit_gb'] > 0): ?>
                <button class="btn btn-outline-info btn-sm w-100" onclick="resetTraffic(<?= $account['id'] ?>, <?= $account['data_used_bytes'] ?>)">
                    <i class="bi bi-arrow-repeat"></i> 重置流量
                    <small>(¥<?= number_format(\App\Services\VpnAccountService::getTrafficResetPriceStatic(), 2) ?>)</small>
                </button>
                <?php endif; ?>
            </div>

            <!-- 账户信息 -->
            <div class="col-md-8">
                <table class="table table-sm table-borderless mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width:100px;">到期时间</td>
                            <td>
                                <strong><?= date('Y-m-d H:i', strtotime($account['expire_time'])) ?></strong>
                                <?php
                                $daysLeft = ceil((strtotime($account['expire_time']) - time()) / 86400);
                                if ($daysLeft > 0 && $daysLeft <= 3):
                                ?>
                                <span class="badge bg-warning ms-1">剩<?= $daysLeft ?>天</span>
                                <?php elseif ($daysLeft <= 0): ?>
                                <span class="badge bg-danger ms-1">已过期</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">速率</td>
                            <td>↓<?= round($account['down_rate'] / 1024, 1) ?> Mbps / ↑<?= round($account['up_rate'] / 1024, 1) ?> Mbps</td>
                        </tr>
                        <tr>
                            <td class="text-muted">并发连接</td>
                            <td><?= $account['active_num'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">已用流量</td>
                            <td>
                                <?= formatTrafficBytes($account['data_used_bytes']) ?>
                                <?php if ($account['data_limit_gb'] > 0): ?>
                                <span class="text-muted">/ <?= $account['data_limit_gb'] ?> GB</span>
                                <?php
                                $pct = min(100, round($account['data_used_bytes'] / ($account['data_limit_gb'] * 1024 * 1024 * 1024) * 100, 1));
                                $barColor = $pct >= 90 ? 'bg-danger' : ($pct >= 70 ? 'bg-warning' : 'bg-success');
                                ?>
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar <?= $barColor ?>" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <?php else: ?>
                                <span class="badge bg-success ms-1">不限流量</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($account['last_online_at']): ?>
                        <tr>
                            <td class="text-muted">最后在线</td>
                            <td><small><?= date('Y-m-d H:i', strtotime($account['last_online_at'])) ?></small></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($account['status'] === 'traffic_exceeded'): ?>
                <div class="alert alert-danger py-2 mt-2 mb-0">
                    <small>
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>流量已用完!</strong>
                        <?php if (!empty($otherSubs)): ?>
                            请在下方「我的套餐订阅」中切换到其他可用套餐,
                            或 <a href="#" onclick="resetTraffic(<?= $account['id'] ?>, <?= $account['data_used_bytes'] ?>); return false;" class="alert-link">付费重置流量</a>
                            | <a href="/packages" class="alert-link">购买新套餐</a>
                        <?php else: ?>
                            请 <a href="#" onclick="resetTraffic(<?= $account['id'] ?>, <?= $account['data_used_bytes'] ?>); return false;" class="alert-link">付费重置流量</a>
                            或 <a href="/packages" class="alert-link">购买新套餐</a>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($activeSub || !empty($otherSubs)): ?>
        <!-- 我的套餐订阅 -->
        <div class="mt-3">
            <h6 class="text-muted mb-2"><i class="bi bi-collection"></i> 我的套餐订阅</h6>
            <div class="table-responsive">
                <table class="table table-sm table-borderless align-middle">
                    <thead class="text-muted" style="font-size: 0.75rem; text-transform: uppercase;">
                        <tr>
                            <th>套餐</th>
                            <th>周期</th>
                            <th>速率(下/上)</th>
                            <th>流量限制</th>
                            <th>剩余时间</th>
                            <th>到期时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($activeSub): ?>
                        <tr style="background: #eef2ff;">
                            <td><strong><?= htmlspecialchars($activeSub['package_name']) ?></strong></td>
                            <td><small><?= $activeSub['billing_cycle'] ?? '-' ?></small></td>
                            <td><small><?= round($activeSub['down_rate']/1024,1) ?>/<?= round($activeSub['up_rate']/1024,1) ?> Mbps</small></td>
                            <td>
                                <?php if ($activeSub['data_limit_gb'] > 0): ?>
                                <small><?= formatTrafficBytes($activeSub['data_used_bytes']) ?> / <?= $activeSub['data_limit_gb'] ?>GB</small>
                                <?php else: ?>
                                <span class="badge bg-success">不限</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $daysLeft = ceil((strtotime($activeSub['expire_time']) - time()) / 86400);
                                $badgeClass = $daysLeft <= 3 ? 'bg-danger' : ($daysLeft <= 7 ? 'bg-warning' : 'bg-success');
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $daysLeft > 0 ? $daysLeft . '天' : '已过期' ?></span>
                            </td>
                            <td><small><?= date('Y-m-d', strtotime($activeSub['expire_time'])) ?></small></td>
                            <td><span class="badge bg-primary"><i class="bi bi-check-circle"></i> 使用中</span></td>
                            <td>-</td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($otherSubs as $oSub): ?>
                        <tr>
                            <td><?= htmlspecialchars($oSub['package_name']) ?></td>
                            <td><small><?= $oSub['billing_cycle'] ?? '-' ?></small></td>
                            <td><small><?= round($oSub['down_rate']/1024,1) ?>/<?= round($oSub['up_rate']/1024,1) ?> Mbps</small></td>
                            <td>
                                <?php if ($oSub['data_limit_gb'] > 0): ?>
                                <small><?= formatTrafficBytes($oSub['data_used_bytes']) ?> / <?= $oSub['data_limit_gb'] ?>GB</small>
                                <?php else: ?>
                                <span class="badge bg-success">不限</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $daysLeft = ceil((strtotime($oSub['expire_time']) - time()) / 86400);
                                $badgeClass = $daysLeft <= 3 ? 'bg-danger' : ($daysLeft <= 7 ? 'bg-warning' : 'bg-secondary');
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= $daysLeft > 0 ? $daysLeft . '天' : '已过期' ?></span>
                            </td>
                            <td><small><?= date('Y-m-d', strtotime($oSub['expire_time'])) ?></small></td>
                            <td><span class="badge bg-light text-dark">待使用</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="switchSubscription(<?= $account['id'] ?>, <?= $oSub['id'] ?>)">
                                    <i class="bi bi-arrow-left-right"></i> 切换
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($otherSubs) > 0): ?>
            <div class="alert alert-secondary py-1 px-2 mb-0">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i>
                    切换套餐后, VPN参数将立即变更。原套餐剩余时间不丢失, 可随时切回。
                </small>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- 在线设备 -->
<?php if (!empty($sessions)): ?>
<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-laptop text-primary"></i> 在线设备 (<?= count($sessions) ?>)</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>账号</th>
                        <th>IP地址</th>
                        <th>开始时间</th>
                        <th>时长</th>
                        <th>流量</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($session['vpn_username'] ?? '-') ?></strong></td>
                        <td><?= htmlspecialchars($session['framed_ipaddr'] ?? '-') ?></td>
                        <td><small><?= isset($session['acct_start_time']) ? date('Y-m-d H:i', strtotime($session['acct_start_time'])) : '-' ?></small></td>
                        <td><?= formatDuration($session['acct_session_time'] ?? 0) ?></td>
                        <td><?= formatTrafficBytes(($session['acct_input_total'] ?? 0) + ($session['acct_output_total'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 购买新套餐 -->
<div class="card">
    <div class="card-body">
        <h5 class="mb-3"><i class="bi bi-arrow-repeat text-primary"></i> 续费 / 购买新套餐</h5>
        <div class="alert alert-info py-2">
            <small><i class="bi bi-info-circle"></i> 购买套餐时可以选择: 续费到已有账号, 或创建一个新的VPN子账号(适合给不同的人使用)。</small>
        </div>
        <div class="text-center py-3">
            <a href="/packages" class="btn btn-primary btn-lg">
                <i class="bi bi-box-seam"></i> 查看可购买套餐
            </a>
        </div>
    </div>
</div>

<script>
async function showPasswordModal(accountId) {
    document.getElementById('passwordModalAccountId').value = accountId;
    document.getElementById('customPassword').value = '';
    document.getElementById('randomPasswordOption').checked = true;
    document.getElementById('customPasswordGroup').style.display = 'none';
    new bootstrap.Modal(document.getElementById('passwordModal')).show();
}

document.addEventListener('change', function(e) {
    if (e.target.name === 'passwordType') {
        document.getElementById('customPasswordGroup').style.display = 
            e.target.value === 'custom' ? 'block' : 'none';
    }
});

async function submitPasswordChange(event) {
    event.preventDefault();
    const accountId = document.getElementById('passwordModalAccountId').value;
    const passwordType = document.querySelector('input[name="passwordType"]:checked').value;
    const customPassword = document.getElementById('customPassword').value;
    
    if (passwordType === 'custom') {
        if (customPassword.length < 6) {
            showToast('密码长度至少6位', 'error');
            return false;
        }
    }
    
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    if (passwordType === 'custom') {
        formData.append('password', customPassword);
    }
    
    const response = await fetch('/vpn-account/' + accountId + '/reset-password', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
        showToast('密码修改成功: ' + result.password);
        setTimeout(() => window.location.reload(), 2000);
    } else {
        showToast(result.message || '修改失败', 'error');
    }
    return false;
}

async function resetTraffic(accountId, usedBytes) {
    const usedGB = (usedBytes / 1024 / 1024 / 1024).toFixed(2);
    const resetPrice = <?= \App\Services\VpnAccountService::getTrafficResetPriceStatic() ?>;
    
    if (usedBytes <= 0) {
        showToast('当前没有已用流量, 无需重置', 'error');
        return;
    }
    
    if (!confirm(`确认重置流量?\n\n已用流量: ${usedGB} GB\n重置费用: ¥${resetPrice}\n(将从余额中扣除)\n\n重置后已用流量清零, 可继续使用。`)) return;
    
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    
    const response = await fetch('/vpn-account/' + accountId + '/reset-traffic', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || '流量重置成功');
        setTimeout(() => window.location.reload(), 1500);
    } else {
        showToast(result.message || '重置失败', 'error');
        if (result.message && result.message.includes('余额不足')) {
            setTimeout(() => window.location.href = '/recharge', 1500);
        }
    }
}

async function switchSubscription(accountId, subscriptionId) {
    if (!confirm('确认切换到此套餐?\n\n切换后VPN参数将立即变更, 原套餐剩余时间不会丢失, 可随时切换回来。')) return;
    
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('subscription_id', subscriptionId);
    
    const response = await fetch('/vpn-account/' + accountId + '/switch-subscription', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast(result.message || '切换成功');
        setTimeout(() => window.location.reload(), 1500);
    } else {
        showToast(result.message || '切换失败', 'error');
    }
}
</script>

<!-- 修改密码弹窗 -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key"></i> 修改VPN密码</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form onsubmit="return submitPasswordChange(event)">
                    <input type="hidden" id="passwordModalAccountId">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="passwordType" id="randomPasswordOption" value="random" checked>
                            <label class="form-check-label" for="randomPasswordOption">
                                <i class="bi bi-shuffle"></i> 系统随机生成密码
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="passwordType" id="customPasswordOption" value="custom">
                            <label class="form-check-label" for="customPasswordOption">
                                <i class="bi bi-pencil"></i> 自己输入新密码
                            </label>
                        </div>
                    </div>
                    <div class="mb-3" id="customPasswordGroup" style="display:none;">
                        <label class="form-label">新密码 (6-128位)</label>
                        <input type="text" class="form-control" id="customPassword" 
                               minlength="6" maxlength="128" placeholder="请输入新密码"
                               style="font-family: monospace;">
                        <small class="text-muted">密码长度6-128位, 建议使用字母和数字组合</small>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> 确认修改</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
