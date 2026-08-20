<?php
/** @var array $tickets */
/** @var int $page */
/** @var int $totalPages */
/** @var int $total */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-life-preserver"></i> 我的工单</h1>
    <a href="/tickets/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> 提交工单</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($tickets)): ?>
        <div class="text-center py-5">
            <i class="bi bi-life-preserver display-4 text-muted"></i>
            <h5 class="mt-3 text-muted">暂无工单</h5>
            <p class="text-muted">遇到问题? 提交工单获取帮助</p>
            <a href="/tickets/create" class="btn btn-primary">提交工单</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>工单号</th>
                        <th>标题</th>
                        <th>分类</th>
                        <th>优先级</th>
                        <th>状态</th>
                        <th>最后回复</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr style="cursor:pointer" onclick="window.location='/tickets/<?= $ticket['id'] ?>'">
                        <td><small class="font-monospace"><?= htmlspecialchars($ticket['ticket_no']) ?></small></td>
                        <td><?= htmlspecialchars($ticket['subject']) ?></td>
                        <td>
                            <?php
                            $catMap = ['general' => '通用', 'billing' => '计费', 'connection' => '连接', 'other' => '其他'];
                            $catIcons = ['general' => 'bi-chat', 'billing' => 'bi-cash', 'connection' => 'bi-wifi', 'other' => 'bi-three-dots'];
                            echo '<span class="badge bg-light text-dark"><i class="bi ' . ($catIcons[$ticket['category']] ?? 'bi-chat') . '"></i> ' . ($catMap[$ticket['category']] ?? $ticket['category']) . '</span>';
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
                            $statusMap = [
                                'open' => '<span class="badge bg-primary">待回复</span>',
                                'replied' => '<span class="badge bg-success">已回复</span>',
                                'closed' => '<span class="badge bg-secondary">已关闭</span>',
                            ];
                            echo $statusMap[$ticket['status']] ?? $ticket['status'];
                            ?>
                        </td>
                        <td><small><?= $ticket['last_reply_at'] ? date('m-d H:i', strtotime($ticket['last_reply_at'])) : '-' ?></small></td>
                        <td><small><?= date('Y-m-d H:i', strtotime($ticket['created_at'])) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
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
        <?php endif; ?>
    </div>
</div>
