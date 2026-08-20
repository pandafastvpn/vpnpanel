<?php
/** @var array $tutorials */
/** @var array $categories */
/** @var string $currentCategory */
/** @var array|null $currentUser */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-book"></i> 使用教程</h1>
    <a href="/" class="btn btn-outline-secondary btn-sm">返回首页</a>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="/tutorials" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= empty($currentCategory) ? 'active' : '' ?>">
                        <span><i class="bi bi-grid"></i> 全部</span>
                        <span class="badge bg-secondary"><?= count($tutorials) ?></span>
                    </a>
                    <?php foreach ($categories as $key => $label): 
                        $count = count(array_filter($tutorials, fn($t) => $t['category'] === $key));
                        if ($count === 0 && $key !== $currentCategory) continue;
                    ?>
                    <a href="/tutorials?category=<?= $key ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $currentCategory === $key ? 'active' : '' ?>">
                        <span>
                            <?php
                            $icons = ['windows' => 'bi-windows', 'mac' => 'bi-apple', 'ios' => 'bi-phone', 'android' => 'bi-android', 'router' => 'bi-router', 'general' => 'bi-info-circle'];
                            echo '<i class="bi ' . ($icons[$key] ?? 'bi-book') . '"></i> ' . $label;
                            ?>
                        </span>
                        <span class="badge bg-secondary"><?= $count ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if (empty($tutorials)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-book display-4 text-muted"></i>
                <h5 class="mt-3 text-muted">暂无教程</h5>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($tutorials as $tutorial): ?>
            <div class="col-md-6 mb-3">
                <div class="card h-100 tutorial-card" style="cursor:pointer" onclick="window.location='/tutorials/<?= htmlspecialchars($tutorial['slug']) ?>'">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3" style="font-size:1.5rem">
                                <?php
                                $icons = ['windows' => 'bi-windows text-primary', 'mac' => 'bi-apple text-dark', 'ios' => 'bi-phone text-primary', 'android' => 'bi-android text-success', 'router' => 'bi-router text-info', 'general' => 'bi-info-circle text-warning'];
                                echo '<i class="bi ' . ($icons[$tutorial['category']] ?? 'bi-book') . '"></i>';
                                ?>
                            </div>
                            <div>
                                <h6 class="mb-0"><?= htmlspecialchars($tutorial['title']) ?></h6>
                                <small class="text-muted">
                                    <?= $categories[$tutorial['category']] ?? $tutorial['category'] ?> · 
                                    <i class="bi bi-eye"></i> <?= $tutorial['views'] ?> 次浏览
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
