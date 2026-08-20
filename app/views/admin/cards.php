<?php
/** @var array $cards */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $search */
/** @var string $status */
/** @var string $batchNo */
/** @var array $batches */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-ticket"></i> 卡密管理</h1>
    <button class="btn btn-primary btn-sm" onclick="showGenerateModal()"><i class="bi bi-plus-circle"></i> 生成卡密</button>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">总卡密</h6>
                <h3><?= $total ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">未使用</h6>
                <h3 class="text-success"><?= $total - ($total - count(array_filter($cards, fn($c) => $c['status'] === 'unused'))) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">当前页</h6>
                <h3><?= count($cards) ?></h3>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($batches)): ?>
<div class="card mb-4">
    <div class="card-body">
        <h6 class="mb-3"><i class="bi bi-folder text-primary"></i> 最近批次</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>批次号</th><th>总数</th><th>未使用</th><th>已使用</th><th>创建时间</th><th>操作</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td class="font-monospace"><?= htmlspecialchars($batch['batch_no']) ?></td>
                        <td><?= $batch['count'] ?></td>
                        <td class="text-success"><?= $batch['unused'] ?></td>
                        <td class="text-muted"><?= $batch['used'] ?></td>
                        <td><small><?= date('Y-m-d H:i', strtotime($batch['created_at'])) ?></small></td>
                        <td>
                            <a href="/admin/cards/export?batch=<?= urlencode($batch['batch_no']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i> 导出
                            </a>
                            <a href="/admin/cards?batch=<?= urlencode($batch['batch_no']) ?>" class="btn btn-sm btn-outline-secondary">
                                查看
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="搜索卡密">
            </div>
            <div class="col-md-3">
                <select class="form-control" name="status">
                    <option value="">全部状态</option>
                    <option value="unused" <?= $status === 'unused' ? 'selected' : '' ?>>未使用</option>
                    <option value="used" <?= $status === 'used' ? 'selected' : '' ?>>已使用</option>
                    <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>已禁用</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">搜索</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/cards" class="btn btn-outline-secondary w-100">重置</a>
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
                        <th>卡密</th>
                        <th>面值</th>
                        <th>状态</th>
                        <th>批次</th>
                        <th>使用人</th>
                        <th>使用时间</th>
                        <th>过期</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                    <tr>
                        <td><?= $card['id'] ?></td>
                        <td><code class="font-monospace"><?= htmlspecialchars($card['card_no']) ?></code></td>
                        <td>¥<?= number_format($card['amount'], 2) ?></td>
                        <td>
                            <?php
                            $statusMap = ['unused' => '<span class="badge bg-success">未使用</span>', 'used' => '<span class="badge bg-secondary">已使用</span>', 'disabled' => '<span class="badge bg-danger">已禁用</span>'];
                            echo $statusMap[$card['status']] ?? $card['status'];
                            ?>
                        </td>
                        <td><small class="font-monospace"><?= htmlspecialchars($card['batch_no'] ?? '-') ?></small></td>
                        <td><small><?= $card['used_by'] ?? '-' ?></small></td>
                        <td><small><?= $card['used_at'] ? date('Y-m-d H:i', strtotime($card['used_at'])) : '-' ?></small></td>
                        <td><small><?= $card['expire_at'] ? date('Y-m-d', strtotime($card['expire_at'])) : '永久' ?></small></td>
                        <td><small><?= date('Y-m-d H:i', strtotime($card['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cards)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">暂无卡密</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&batch=<?= urlencode($batchNo) ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&batch=<?= urlencode($batchNo) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&batch=<?= urlencode($batchNo) ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- 生成卡密弹窗 -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">生成卡密</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="generateForm" onsubmit="return submitGenerate(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">面值 (元)</label>
                        <input type="number" class="form-control" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">数量</label>
                        <input type="number" class="form-control" name="count" min="1" max="1000" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">有效期 (天, 0=永久)</label>
                        <input type="number" class="form-control" name="expire_days" min="0" value="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">生成</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 生成结果弹窗 -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">生成结果</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="generateResult"></div>
            </div>
        </div>
    </div>
</div>

<script>
function showGenerateModal() {
    document.getElementById('generateForm').reset();
    new bootstrap.Modal(document.getElementById('generateModal')).show();
}

function submitGenerate(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('/admin/cards/generate', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('generateModal')).hide();
                
                let html = `<p class="text-success"><i class="bi bi-check-circle"></i> ${result.message}</p>`;
                html += '<textarea class="form-control font-monospace" rows="15" id="cardList">';
                result.cards.forEach(c => html += c + '\n');
                html += '</textarea>';
                html += '<button class="btn btn-primary mt-2 w-100" onclick="copyCardList()">复制全部</button>';
                
                document.getElementById('generateResult').innerHTML = html;
                new bootstrap.Modal(document.getElementById('resultModal')).show();
            } else {
                showToast(result.message || '生成失败', 'error');
            }
        });
    return false;
}

function copyCardList() {
    const text = document.getElementById('cardList').value;
    navigator.clipboard.writeText(text).then(() => showToast('已复制'));
}
</script>
