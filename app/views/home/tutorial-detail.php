<?php
/** @var array $tutorial */
/** @var array $categories */
/** @var array $related */
?>
<div class="topbar">
    <h1><i class="bi bi-book"></i> <?= htmlspecialchars($tutorial['title']) ?></h1>
    <a href="/tutorials" class="btn btn-outline-secondary btn-sm">返回列表</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="mb-3">
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-folder"></i> <?= $categories[$tutorial['category']] ?? $tutorial['category'] ?>
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="bi bi-eye"></i> <?= $tutorial['views'] ?> 次浏览
                    </span>
                    <small class="text-muted ms-2">更新于 <?= date('Y-m-d', strtotime($tutorial['updated_at'])) ?></small>
                </div>
                <hr>
                <div class="tutorial-content">
                    <?= $tutorial['content'] ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="/tutorials" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> 返回教程列表
            </a>
            <?php if (!empty($related)): ?>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-link"></i> 相关教程
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($related as $r): ?>
                    <li><a class="dropdown-item" href="/tutorials/<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['title']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <h6><i class="bi bi-life-preserver text-primary"></i> 需要更多帮助?</h6>
                <p class="small text-muted">如果教程没有解决您的问题, 可以提交工单获取支持。</p>
                <a href="/tickets/create" class="btn btn-outline-primary btn-sm w-100">提交工单</a>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h6><i class="bi bi-collection text-primary"></i> 其他教程</h6>
                <div class="list-group list-group-flush">
                    <?php 
                    $allTutorials = $tutorial; // just to use variable
                    foreach ($categories as $key => $label): ?>
                    <a href="/tutorials?category=<?= $key ?>" class="list-group-item list-group-item-action small">
                        <?php
                        $icons = ['windows' => 'bi-windows', 'mac' => 'bi-apple', 'ios' => 'bi-phone', 'android' => 'bi-android', 'router' => 'bi-router', 'general' => 'bi-info-circle'];
                        echo '<i class="bi ' . ($icons[$key] ?? 'bi-book') . '"></i> ' . $label;
                        ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
