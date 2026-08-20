<?php
/** @var array $coupons */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $search */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-tag"></i> 优惠码管理</h1>
    <button class="btn btn-primary btn-sm" onclick="showCreateModal()"><i class="bi bi-plus-circle"></i> 创建优惠码</button>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-8">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索优惠码">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/coupons" class="btn btn-outline-secondary w-100">重置</a>
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
                        <th>优惠码</th>
                        <th>名称</th>
                        <th>类型</th>
                        <th>折扣值</th>
                        <th>最低消费</th>
                        <th>有效期</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c): ?>
                    <tr>
                        <td><?= $c['id'] ?></td>
                        <td><code class="font-monospace fw-bold text-primary"><?= htmlspecialchars($c['code']) ?></code></td>
                        <td><?= htmlspecialchars($c['name'] ?? '-') ?></td>
                        <td>
                            <?php if ($c['discount_type'] === 'percent'): ?>
                            <span class="badge bg-info">百分比</span>
                            <?php else: ?>
                            <span class="badge bg-primary">固定金额</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['discount_type'] === 'percent'): ?>
                            <?= (float)$c['discount_value'] ?>%
                            <?php else: ?>
                            ¥<?= number_format((float)$c['discount_value'], 2) ?>
                            <?php endif; ?>
                        </td>
                        <td>¥<?= number_format((float)$c['min_amount'], 2) ?></td>
                        <td>
                            <?php if ($c['expires_at']): ?>
                            <small><?= date('Y-m-d', strtotime($c['expires_at'])) ?></small>
                            <?php else: ?>
                            <small class="text-muted">永久</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$c['status'] === 1): ?>
                            <span class="badge bg-success">启用</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">停用</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($c['created_at'])) ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-warning" onclick="toggleCoupon(<?= $c['id'] ?>, <?= (int)$c['status'] === 1 ? 0 : 1 ?>)">
                                <?= (int)$c['status'] === 1 ? '停用' : '启用' ?>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCoupon(<?= $c['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                    <tr><td colspan="10" class="text-center text-muted py-3">暂无优惠码</td></tr>
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

<!-- 创建优惠码弹窗 -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">创建优惠码</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createForm" onsubmit="return submitCreate(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">优惠码 <small class="text-muted">(大写字母+数字)</small></label>
                        <input type="text" class="form-control font-monospace text-uppercase" name="code" placeholder="如: SUMMER20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">名称/备注</label>
                        <input type="text" class="form-control" name="name" placeholder="如: 夏季促销9折">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">折扣类型</label>
                            <select class="form-select" name="discount_type" onchange="toggleDiscountLabel(this.value)">
                                <option value="fixed">固定金额 (元)</option>
                                <option value="percent">百分比 (%)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" id="discountLabel">折扣值</label>
                            <input type="number" class="form-control" name="discount_value" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">最低消费 (元, 0=不限)</label>
                            <input type="number" class="form-control" name="min_amount" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">限定套餐ID (0=不限)</label>
                            <input type="number" class="form-control" name="package_id" min="0" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">生效时间 (可空)</label>
                            <input type="datetime-local" class="form-control" name="starts_at">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">过期时间 (可空)</label>
                            <input type="datetime-local" class="form-control" name="expires_at">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="status" value="1" checked>
                            <label class="form-check-label">立即启用</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">创建</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDiscountLabel(type) {
    const label = document.getElementById('discountLabel');
    label.textContent = type === 'percent' ? '折扣百分比 (%)' : '折扣金额 (元)';
}

function showCreateModal() {
    document.getElementById('createForm').reset();
    new bootstrap.Modal(document.getElementById('createModal')).show();
}

async function submitCreate(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const response = await fetch('/admin/coupons/create', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('createModal')).hide();
        showToast(result.message || '创建成功');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(result.message || '创建失败', 'error');
    }
    return false;
}

async function toggleCoupon(id, status) {
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('status', status);
    const response = await fetch('/admin/coupons/' + id + '/update', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
    if (result.success) setTimeout(() => location.reload(), 800);
}

async function deleteCoupon(id) {
    if (!confirm('确认删除此优惠码?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    const response = await fetch('/admin/coupons/' + id + '/delete', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '已删除' : '删除失败'), result.success ? 'success' : 'error');
    if (result.success) setTimeout(() => location.reload(), 800);
}
</script>
