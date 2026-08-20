<?php
/** @var array $logs */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
?>
<div class="topbar">
    <h1><i class="bi bi-journal-text"></i> 操作日志</h1>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>操作人</th>
                        <th>操作</th>
                        <th>对象</th>
                        <th>详情</th>
                        <th>IP</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><small><?= htmlspecialchars($log['email'] ?? 'ID:' . $log['user_id']) ?></small></td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($log['action']) ?></span></td>
                        <td><small><?= htmlspecialchars($log['target'] ?? '-') ?></small></td>
                        <td><small><?= htmlspecialchars($log['detail'] ?? '-') ?></small></td>
                        <td><small><?= htmlspecialchars($log['ip']) ?></small></td>
                        <td><small><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">暂无日志</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>">上一页</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>">下一页</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>
