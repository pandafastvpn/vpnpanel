<?php
/** @var array $tutorials */
/** @var array $categories */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-book"></i> 教程管理</h1>
    <button class="btn btn-primary btn-sm" onclick="showCreateModal()"><i class="bi bi-plus-circle"></i> 新建教程</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>URL别名</th>
                        <th>分类</th>
                        <th>浏览量</th>
                        <th>排序</th>
                        <th>状态</th>
                        <th>更新时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tutorials as $tut): ?>
                    <tr>
                        <td><?= $tut['id'] ?></td>
                        <td><?= htmlspecialchars($tut['title']) ?></td>
                        <td><small class="font-monospace"><?= htmlspecialchars($tut['slug']) ?></small></td>
                        <td><span class="badge bg-light text-dark"><?= $categories[$tut['category']] ?? $tut['category'] ?></span></td>
                        <td><?= $tut['views'] ?></td>
                        <td><?= $tut['sort_order'] ?></td>
                        <td>
                            <?php if ($tut['status'] == 1): ?>
                                <span class="badge bg-success">显示</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">隐藏</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= date('Y-m-d', strtotime($tut['updated_at'])) ?></small></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" onclick='editTutorial(<?= json_encode($tut, JSON_UNESCAPED_UNICODE) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteTutorial(<?= $tut['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tutorials)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-3">暂无教程</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 教程编辑弹窗 -->
<div class="modal fade" id="tutorialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tutorialModalTitle">新建教程</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="tutorialForm" onsubmit="return submitTutorial(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <input type="hidden" name="tutorial_id" id="tutorialId">
                    <div class="mb-3">
                        <label class="form-label">标题</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">URL别名</label>
                            <input type="text" class="form-control" name="slug" placeholder="如: windows-guide">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">分类</label>
                            <select class="form-control" name="category">
                                <?php foreach ($categories as $key => $label): ?>
                                <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">排序</label>
                            <input type="number" class="form-control" name="sort_order" value="0" min="0">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">状态</label>
                            <select class="form-control" name="status">
                                <option value="1">显示</option>
                                <option value="0">隐藏</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">内容 (支持HTML)</label>
                        <textarea class="form-control" name="content" rows="12" required style="font-family: monospace;" placeholder="<h2>标题</h2>&#10;<p>内容...</p>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">保存</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('tutorialModalTitle').textContent = '新建教程';
    document.getElementById('tutorialId').value = '';
    document.getElementById('tutorialForm').reset();
    new bootstrap.Modal(document.getElementById('tutorialModal')).show();
}

function editTutorial(tut) {
    document.getElementById('tutorialModalTitle').textContent = '编辑教程';
    document.getElementById('tutorialId').value = tut.id;
    const form = document.getElementById('tutorialForm');
    form.title.value = tut.title;
    form.slug.value = tut.slug;
    form.category.value = tut.category;
    form.sort_order.value = tut.sort_order;
    form.status.value = tut.status;
    form.content.value = tut.content;
    new bootstrap.Modal(document.getElementById('tutorialModal')).show();
}

function submitTutorial(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const tutId = formData.get('tutorial_id');
    
    let url = '/admin/tutorials/create';
    if (tutId) {
        url = '/admin/tutorials/' + tutId + '/update';
    }
    
    fetch(url, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '操作成功' : '操作失败'), result.success ? 'success' : 'error');
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('tutorialModal')).hide();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    return false;
}

function deleteTutorial(id) {
    if (!confirm('确认删除此教程?')) return;
    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    fetch('/admin/tutorials/' + id + '/delete', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            showToast(result.message || (result.success ? '删除成功' : '删除失败'), result.success ? 'success' : 'error');
            if (result.success) setTimeout(() => window.location.reload(), 1000);
        });
}
</script>
