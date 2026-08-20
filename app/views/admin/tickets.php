<?php
/** @var array $tickets */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $status */
/** @var string $category */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-life-preserver"></i> 工单管理</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <select class="form-control" name="status">
                    <option value="">全部状态</option>
                    <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>待回复</option>
                    <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>已回复</option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>已关闭</option>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-control" name="category">
                    <option value="">全部分类</option>
                    <option value="general" <?= $category === 'general' ? 'selected' : '' ?>>通用</option>
                    <option value="connection" <?= $category === 'connection' ? 'selected' : '' ?>>连接问题</option>
                    <option value="billing" <?= $category === 'billing' ? 'selected' : '' ?>>计费问题</option>
                    <option value="other" <?= $category === 'other' ? 'selected' : '' ?>>其他</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">筛选</button>
            </div>
            <div class="col-md-2">
                <a href="/admin/tickets" class="btn btn-outline-secondary w-100">重置</a>
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
                        <th>工单号</th>
                        <th>用户</th>
                        <th>标题</th>
                        <th>分类</th>
                        <th>优先级</th>
                        <th>状态</th>
                        <th>最后回复</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr style="cursor:pointer" onclick="window.location='/admin/tickets/<?= $ticket['id'] ?>'">
                        <td><small class="font-monospace"><?= htmlspecialchars($ticket['ticket_no']) ?></small></td>
                        <td><small><?= htmlspecialchars($ticket['email'] ?? 'ID:'.$ticket['user_id']) ?></small></td>
                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                        <td>
                            <?php
                            $catMap = ['general' => '通用', 'billing' => '计费', 'connection' => '连接', 'other' => '其他'];
                            echo $catMap[$ticket['category']] ?? $ticket['category'];
                            ?>
                        </td>
                        <td>
                            <?php
                            $priMap = ['low' => '<span class="badge bg-secondary">低</span>', 'normal' => '<span class="badge bg-info">普通</span>', 'high' => '<span class="badge bg-warning">高</span>', 'urgent' => '<span class="badge bg-danger">紧急</span>'];
                            echo $priMap[$ticket['priority']] ?? $ticket['priority'];
                            ?>
                        </td>
                        <td>
                            <?php
                            $statusMap = ['open' => '<span class="badge bg-primary">待回复</span>', 'replied' => '<span class="badge bg-success">已回复</span>', 'closed' => '<span class="badge bg-secondary">已关闭</span>'];
                            echo $statusMap[$ticket['status']] ?? $ticket['status'];
                            ?>
                        </td>
                        <td>
                            <?php if ($ticket['last_reply_by'] == 1): ?>
                                <small class="text-success"><i class="bi bi-headset"></i> 客服</small>
                            <?php else: ?>
                                <small class="text-muted"><i class="bi bi-person"></i> 用户</small>
                            <?php endif; ?>
                            <small class="d-block text-muted"><?= $ticket['last_reply_at'] ? date('m-d H:i', strtotime($ticket['last_reply_at'])) : '-' ?></small>
                        </td>
                        <td><small><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tickets)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">暂无工单</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($status) ?>&category=<?= urlencode($category) ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
