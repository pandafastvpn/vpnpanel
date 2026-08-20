<?php
/** @var array $ticket */
/** @var array $replies */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-chat-dots"></i> <?= htmlspecialchars($ticket['subject']) ?></h1>
    <a href="/tickets" class="btn btn-outline-secondary btn-sm">返回列表</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div id="replies-container">
            <?php foreach ($replies as $reply): ?>
            <div class="card mb-3 <?= $reply['is_staff'] ? 'border-primary' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <?php if ($reply['is_staff']): ?>
                                <span class="badge bg-primary"><i class="bi bi-headset"></i> 客服</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-person"></i> 用户</span>
                            <?php endif; ?>
                            <small class="text-muted ms-2"><?= htmlspecialchars($reply['email'] ?? '') ?></small>
                        </div>
                        <small class="text-muted"><?= date('Y-m-d H:i', strtotime($reply['created_at'])) ?></small>
                    </div>
                    <div class="reply-content">
                        <?= nl2br(htmlspecialchars($reply['content'])) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($ticket['status'] !== 'closed'): ?>
        <div class="card">
            <div class="card-body">
                <form onsubmit="return replyTicket(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">回复内容</label>
                        <textarea class="form-control" name="content" rows="4" required 
                                  placeholder="请输入回复内容..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="replyBtn">
                            <i class="bi bi-reply"></i> 回复
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="closeTicket()">
                            <i class="bi bi-check-circle"></i> 关闭工单
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-4">
                <i class="bi bi-check-circle text-success" style="font-size: 2rem"></i>
                <h5 class="mt-2">此工单已关闭</h5>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6 class="mb-3">工单信息</h6>
                <table class="table table-sm table-borderless">
                    <tbody>
                        <tr>
                            <td class="text-muted">工单号</td>
                            <td><small class="font-monospace"><?= htmlspecialchars($ticket['ticket_no']) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">分类</td>
                            <td>
                                <?php
                                $catMap = ['general' => '通用', 'billing' => '计费', 'connection' => '连接', 'other' => '其他'];
                                echo $catMap[$ticket['category']] ?? $ticket['category'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">优先级</td>
                            <td>
                                <?php
                                $priMap = ['low' => '<span class="badge bg-secondary">低</span>', 'normal' => '<span class="badge bg-info">普通</span>', 'high' => '<span class="badge bg-warning">高</span>', 'urgent' => '<span class="badge bg-danger">紧急</span>'];
                                echo $priMap[$ticket['priority']] ?? $ticket['priority'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">状态</td>
                            <td>
                                <?php
                                $statusMap = ['open' => '<span class="badge bg-primary">待回复</span>', 'replied' => '<span class="badge bg-success">已回复</span>', 'closed' => '<span class="badge bg-secondary">已关闭</span>'];
                                echo $statusMap[$ticket['status']] ?? $ticket['status'];
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">创建时间</td>
                            <td><small><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></small></td>
                        </tr>
                        <?php if ($ticket['last_reply_at']): ?>
                        <tr>
                            <td class="text-muted">最后回复</td>
                            <td><small><?= date('Y-m-d H:i', strtotime($ticket['last_reply_at'])) ?></small></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h6><i class="bi bi-book text-primary"></i> 需要帮助?</h6>
                <p class="small text-muted">查看使用教程获取常见问题的解决方案。</p>
                <a href="/tutorials" class="btn btn-outline-primary btn-sm w-100">查看教程</a>
            </div>
        </div>
    </div>
</div>

<script>
async function replyTicket(event) {
    event.preventDefault();
    const btn = document.getElementById('replyBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 回复中...';
    
    const formData = new FormData(event.target);
    const response = await fetch('/tickets/<?= $ticket["id"] ?>/reply', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast('回复成功');
        setTimeout(() => window.location.reload(), 1000);
    } else {
        showToast(result.message || '回复失败', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-reply"></i> 回复';
    }
    return false;
}

function closeTicket() {
    if (!confirm('确认关闭此工单?')) return;
    apiPost('/tickets/<?= $ticket["id"] ?>/close', {}).then(result => {
        showToast(result.message || '操作成功', result.success ? 'success' : 'error');
        if (result.success) setTimeout(() => window.location.reload(), 1000);
    });
}
</script>
