<?php
/** @var array $packages */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-box-seam"></i> 套餐管理</h1>
    <button class="btn btn-primary btn-sm" onclick="showCreateModal()"><i class="bi bi-plus-circle"></i> 新建套餐</button>
</div>

<?php foreach ($packages as $pkg): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <div>
            <strong><?= htmlspecialchars($pkg['name']) ?></strong>
            <?php if ($pkg['status'] == 1): ?>
            <span class="badge bg-success ms-2">上架</span>
            <?php else: ?>
            <span class="badge bg-secondary ms-2">下架</span>
            <?php endif; ?>
            <?php if (!empty($pkg['radius_profile'])): ?>
            <span class="badge bg-primary ms-1">Profile: <?= htmlspecialchars($pkg['radius_profile']) ?></span>
            <?php endif; ?>
            <small class="text-muted ms-2"><?= htmlspecialchars($pkg['description'] ?? '') ?></small>
        </div>
        <div>
            <button class="btn btn-outline-primary btn-sm" onclick='editPackage(<?= json_encode($pkg, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?>)'>
                <i class="bi bi-pencil"></i> 编辑
            </button>
            <button class="btn btn-outline-danger btn-sm" onclick="deletePackage(<?= $pkg['id'] ?>)">
                <i class="bi bi-trash"></i> 删除
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <div class="col-md-3">
                <small class="text-muted d-block">下载速率</small>
                <strong><?= round($pkg['down_rate'] / 1024, 1) ?> Mbps</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">上传速率</small>
                <strong><?= round($pkg['up_rate'] / 1024, 1) ?> Mbps</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">并发连接</small>
                <strong><?= $pkg['active_num'] ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">流量限制</small>
                <strong><?= $pkg['data_limit'] > 0 ? $pkg['data_limit'] . 'GB' : '不限' ?></strong>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">定价方案</h6>
            <button class="btn btn-sm btn-outline-primary" onclick="showPricingModal(<?= $pkg['id'] ?>)">
                <i class="bi bi-plus"></i> 添加定价
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>周期</th>
                        <th>天数</th>
                        <th>价格</th>
                        <th>原价</th>
                        <th>推荐</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pkg['pricings'] as $pricing): ?>
                    <tr>
                        <td><span class="badge bg-info"><?= getCycleNameAdmin($pricing['billing_cycle']) ?></span></td>
                        <td><?= $pricing['duration_days'] ?>天</td>
                        <td>$<?= number_format($pricing['price'], 2) ?></td>
                        <td><?= $pricing['original_price'] ? '$' . number_format($pricing['original_price'], 2) : '-' ?></td>
                        <td><?= $pricing['is_popular'] ? '<i class="bi bi-star-fill text-warning"></i>' : '-' ?></td>
                        <td><?= $pricing['status'] == 1 ? '<span class="badge bg-success">启用</span>' : '<span class="badge bg-secondary">停用</span>' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editPricing(<?= json_encode($pricing, JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePricing(<?= $pricing['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pkg['pricings'])): ?>
                    <tr><td colspan="7" class="text-center text-muted py-2">暂无定价方案, 请添加</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- 套餐编辑弹窗 -->
<div class="modal fade" id="packageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="packageModalTitle">新建套餐</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="packageForm" onsubmit="return submitPackage(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <input type="hidden" name="package_id" id="packageId">
                    <div class="mb-3">
                        <label class="form-label">套餐名称</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">描述</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">下载速率 (Kbps)</label>
                            <input type="number" class="form-control" name="down_rate" min="0" required value="10240">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">上传速率 (Kbps)</label>
                            <input type="number" class="form-control" name="up_rate" min="0" required value="10240">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">并发连接数</label>
                            <input type="number" class="form-control" name="active_num" min="1" required value="3">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NETORA-Radius Profile 名称 <small class="text-muted">（可留空，留空使用 config.php 的默认 Profile）</small></label>
                        <input type="text" class="form-control" name="radius_profile" maxlength="64" placeholder="例如 standard 或 premium">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">流量限制 (GB, 0=不限)</label>
                            <input type="number" class="form-control" name="data_limit" min="0" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">排序</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">状态</label>
                            <select class="form-control" name="status">
                                <option value="1">上架</option>
                                <option value="0">下架</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">保存</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 定价编辑弹窗 -->
<div class="modal fade" id="pricingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pricingModalTitle">添加定价方案</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="pricingForm" onsubmit="return submitPricing(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <input type="hidden" name="pricing_id" id="pricingId">
                    <input type="hidden" name="package_id" id="pricingPackageId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">计费周期</label>
                            <select class="form-control" name="billing_cycle">
                                <option value="monthly">月付</option>
                                <option value="quarterly">季付</option>
                                <option value="yearly">年付</option>
                                <option value="weekly">周付</option>
                                <option value="biannually">半年付</option>
                                <option value="custom">自定义</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">有效天数</label>
                            <input type="number" class="form-control" name="duration_days" min="1" required value="30">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">价格 ($)</label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">原价 ($, 可选)</label>
                            <input type="number" class="form-control" name="original_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">是否推荐</label>
                            <select class="form-control" name="is_popular">
                                <option value="0">否</option>
                                <option value="1">是</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">排序</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">状态</label>
                            <select class="form-control" name="status">
                                <option value="1">启用</option>
                                <option value="0">停用</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">保存</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('packageModalTitle').textContent = '新建套餐';
    document.getElementById('packageId').value = '';
    document.getElementById('packageForm').reset();
    new bootstrap.Modal(document.getElementById('packageModal')).show();
}

function editPackage(pkg) {
    document.getElementById('packageModalTitle').textContent = '编辑套餐';
    document.getElementById('packageId').value = pkg.id;
    const form = document.getElementById('packageForm');
    form.name.value = pkg.name;
    form.description.value = pkg.description || '';
    form.down_rate.value = pkg.down_rate;
    form.up_rate.value = pkg.up_rate;
    form.active_num.value = pkg.active_num;
    form.data_limit.value = pkg.data_limit || 0;
    form.sort_order.value = pkg.sort_order || 0;
    form.radius_profile.value = pkg.radius_profile || '';
    form.status.value = pkg.status;
    new bootstrap.Modal(document.getElementById('packageModal')).show();
}

function submitPackage(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const pkgId = formData.get('package_id');
    const url = pkgId ? '/admin/packages/' + pkgId + '/update' : '/admin/packages/create';
    fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('packageModal')).hide();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    return false;
}

function deletePackage(pkgId) {
    if (!confirm('确认删除此套餐?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    fetch('/admin/packages/' + pkgId + '/delete', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '删除成功' : '删除失败'), result.success ? 'success' : 'error');
            if (result.success) setTimeout(() => window.location.reload(), 1000);
        });
}

function showPricingModal(packageId) {
    document.getElementById('pricingModalTitle').textContent = '添加定价方案';
    document.getElementById('pricingId').value = '';
    document.getElementById('pricingPackageId').value = packageId;
    document.getElementById('pricingForm').reset();
    new bootstrap.Modal(document.getElementById('pricingModal')).show();
}

function editPricing(pricing) {
    document.getElementById('pricingModalTitle').textContent = '编辑定价方案';
    document.getElementById('pricingId').value = pricing.id;
    document.getElementById('pricingPackageId').value = pricing.package_id;
    const form = document.getElementById('pricingForm');
    form.billing_cycle.value = pricing.billing_cycle;
    form.duration_days.value = pricing.duration_days;
    form.price.value = pricing.price;
    form.original_price.value = pricing.original_price || '';
    form.is_popular.value = pricing.is_popular;
    form.sort_order.value = pricing.sort_order || 0;
    form.status.value = pricing.status;
    new bootstrap.Modal(document.getElementById('pricingModal')).show();
}

function submitPricing(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const pricingId = formData.get('pricing_id');
    const url = pricingId ? '/admin/pricing/' + pricingId + '/update' : '/admin/pricing/create';
    fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('pricingModal')).hide();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    return false;
}

function deletePricing(id) {
    if (!confirm('确认删除此定价方案?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    fetch('/admin/pricing/' + id + '/delete', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '删除成功' : '删除失败'), result.success ? 'success' : 'error');
            if (result.success) setTimeout(() => window.location.reload(), 1000);
        });
}
</script>

<?php
function getCycleNameAdmin($cycle) {
    $map = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付', 'weekly' => '周付', 'biannually' => '半年付', 'custom' => '自定义'];
    return $map[$cycle] ?? $cycle;
}
?>
