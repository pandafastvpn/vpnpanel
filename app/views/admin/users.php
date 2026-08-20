<?php
/** @var array $users */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $search */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-people"></i> 用户管理</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索邮箱/手机/VPN账号">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> 搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/users" class="btn btn-outline-secondary w-100">重置</a>
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
                        <th>邮箱</th>
                        <th>手机</th>
                        <th>余额</th>
                        <th>VPN账号</th>
                        <th>VPN状态</th>
                        <th>到期时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                        <td>¥<?= number_format($user['balance'], 2) ?></td>
                        <td><?= htmlspecialchars($user['vpn_username'] ?? '-') ?></td>
                        <td>
                            <?php if ($user['vpn_status'] ?? null): ?>
                                <?php if ($user['vpn_status'] === 'enabled'): ?>
                                    <span class="badge bg-success">运行中</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">已停用</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-muted">未开通</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['expire_time'] ?? null): ?>
                                <small><?= date('Y-m-d', strtotime($user['expire_time'])) ?></small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['status'] == 1): ?>
                                <span class="badge bg-success">正常</span>
                            <?php else: ?>
                                <span class="badge bg-danger">禁用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-<?= $user['status'] == 1 ? 'warning' : 'success' ?> btn-sm"
                                        onclick="toggleUser(<?= $user['id'] ?>)">
                                    <i class="bi bi-<?= $user['status'] == 1 ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm"
                                        onclick="adjustBalance(<?= $user['id'] ?>, '<?= htmlspecialchars($user['email']) ?>', <?= $user['balance'] ?>)">
                                    <i class="bi bi-cash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">暂无用户</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- 余额调整弹窗 -->
<div class="modal fade" id="balanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">调整余额 - <span id="balanceUserEmail"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>当前余额: <strong id="balanceUserCurrent">¥0.00</strong></p>
                <form id="balanceForm" onsubmit="return submitBalance(event)">
                    <div class="mb-3">
                        <label class="form-label">操作</label>
                        <select class="form-control" name="action" id="balanceAction">
                            <option value="add">增加余额</option>
                            <option value="subtract">扣除余额</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">金额</label>
                        <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-primary">确认</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentUserId = null;

function toggleUser(userId) {
    if (!confirm('确认切换用户状态?')) return;
    apiPost('/admin/users/' + userId + '/toggle', {}).then(result => {
        showToast(result.message || '操作成功');
        if (result.success) setTimeout(() => window.location.reload(), 1000);
    });
}

function adjustBalance(userId, email, balance) {
    currentUserId = userId;
    document.getElementById('balanceUserEmail').textContent = email;
    document.getElementById('balanceUserCurrent').textContent = '¥' + parseFloat(balance).toFixed(2);
    new bootstrap.Modal(document.getElementById('balanceModal')).show();
}

function submitBalance(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    apiPost('/admin/users/' + currentUserId + '/balance', {
        action: formData.get('action'),
        amount: formData.get('amount'),
    }).then(result => {
        showToast(result.message || '操作成功', result.success ? 'success' : 'error');
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('balanceModal')).hide();
            setTimeout(() => window.location.reload(), 1000);
        }
    });
    return false;
}
</script>
