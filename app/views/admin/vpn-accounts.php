<?php
/** @var array $accounts */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $search */
/** @var string $status */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-shield-check"></i> VPN账户管理</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索VPN账号/邮箱">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="status">
                    <option value="">全部状态</option>
                    <option value="enabled" <?= $status === 'enabled' ? 'selected' : '' ?>>运行中</option>
                    <option value="traffic_exceeded" <?= $status === 'traffic_exceeded' ? 'selected' : '' ?>>流量超限</option>
                    <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>已停用</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> 搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/vpn-accounts" class="btn btn-outline-secondary w-100">重置</a>
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
                        <th>ID</th>
                        <th>VPN账号</th>
                        <th>密码</th>
                        <th>用户</th>
                        <th>套餐</th>
                        <th>下载</th>
                        <th>上传</th>
                        <th>并发</th>
                        <th>已用流量</th>
                        <th>到期时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): 
                        $isExpired = strtotime($account['expire_time']) < time();
                    ?>
                    <tr>
                        <td><?= $account['id'] ?></td>
                        <td><strong><?= htmlspecialchars($account['username']) ?></strong></td>
                        <td><code><?= htmlspecialchars($account['password']) ?></code></td>
                        <td><small><?= htmlspecialchars($account['email']) ?></small></td>
                        <td><small><?= htmlspecialchars($account['package_name']) ?></small></td>
                        <td><?= round($account['down_rate'] / 1024, 1) ?>M</td>
                        <td><?= round($account['up_rate'] / 1024, 1) ?>M</td>
                        <td><?= $account['active_num'] ?></td>
                        <td><small><?= formatBytesAdmin($account['data_used_bytes']) ?></small></td>
                        <td>
                            <small class="<?= $isExpired ? 'text-danger' : '' ?>"><?= date('Y-m-d', strtotime($account['expire_time'])) ?></small>
                        </td>
                        <td>
                            <?php if ($account['status'] === 'traffic_exceeded'): ?>
                                <span class="badge bg-danger" title="流量已用完">流量超限</span>
                            <?php elseif ($account['status'] === 'enabled' && !$isExpired): ?>
                                <span class="badge bg-success">运行中</span>
                            <?php elseif ($isExpired): ?>
                                <span class="badge bg-danger">已过期</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">已禁用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-<?= $account['status'] === 'enabled' ? 'warning' : 'success' ?> btn-sm"
                                        onclick="toggleVpnAccount(<?= $account['id'] ?>)">
                                    <i class="bi bi-<?= $account['status'] === 'enabled' ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm"
                                        onclick="resetPassword(<?= $account['id'] ?>)">
                                    <i class="bi bi-key"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm"
                                        onclick="adminResetTraffic(<?= $account['id'] ?>)"
                                        title="重置流量">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm"
                                        onclick="showEditExpireModal(<?= $account['id'] ?>, '<?= date('Y-m-d\TH:i', strtotime($account['expire_time'])) ?>', '<?= htmlspecialchars($account['username']) ?>')"
                                        title="修改到期时间">
                                    <i class="bi bi-calendar-event"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($accounts)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-3">暂无VPN账户</td></tr>
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

<script>
function toggleVpnAccount(id) {
    if (!confirm('确认切换VPN账户状态?')) return;
    apiPost('/admin/vpn-accounts/' + id + '/toggle', {}).then(result => {
        showToast(result.message || '操作成功', result.success ? 'success' : 'error');
        if (result.success) setTimeout(() => window.location.reload(), 1000);
    });
}

function resetPassword(id) {
    if (!confirm('确认重置此VPN账户的密码?')) return;
    apiPost('/admin/vpn-accounts/' + id + '/reset-password', {}).then(result => {
        if (result.success) {
            showToast('新密码: ' + result.password);
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast(result.message || '重置失败', 'error');
        }
    });
}

function adminResetTraffic(id) {
    if (!confirm('确认重置此VPN账户的流量? 已用流量将清零。')) return;
    apiPost('/admin/vpn-accounts/' + id + '/reset-traffic', {}).then(result => {
        showToast(result.message || (result.success ? '重置成功' : '重置失败'), result.success ? 'success' : 'error');
        if (result.success) setTimeout(() => window.location.reload(), 1000);
    });
}
</script>

<?php
function formatBytesAdmin($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>

<!-- 修改到期时间弹窗 -->
<div class="modal fade" id="editExpireModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event"></i> 修改到期时间</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editExpireForm" onsubmit="return submitEditExpire(event)">
                    <input type="hidden" id="editExpireAccountId">
                    <p class="text-muted">账号: <strong id="editExpireUsername"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">新到期时间</label>
                        <input type="datetime-local" class="form-control" id="editExpireTime" required>
                        <small class="text-muted">修改后自动同步到ToughRadius, 用户下次连接即生效</small>
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

<script>
function showEditExpireModal(accountId, currentExpire, username) {
    document.getElementById('editExpireAccountId').value = accountId;
    document.getElementById('editExpireUsername').textContent = username;
    document.getElementById('editExpireTime').value = currentExpire;
    new bootstrap.Modal(document.getElementById('editExpireModal')).show();
}

async function submitEditExpire(event) {
    event.preventDefault();
    const accountId = document.getElementById('editExpireAccountId').value;
    const expireTime = document.getElementById('editExpireTime').value;
    
    if (!expireTime) {
        showToast('请选择到期时间', 'error');
        return false;
    }
    
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('expire_time', expireTime.replace('T', ' ') + ':00');
    
    try {
        const response = await fetch('/admin/vpn-accounts/' + accountId + '/update-expire', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editExpireModal')).hide();
            showToast(result.message);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(result.message || '修改失败', 'error');
        }
    } catch (e) {
        showToast('请求失败', 'error');
    }
    return false;
}
</script>
