<?php
/** @var array $settings */
/** @var string $csrfToken */

// Group settings by prefix
$grouped = [];
foreach ($settings as $s) {
    $key = $s['key_name'];
    if (strpos($key, 'payment_pockyt_') === 0) {
        $grouped['Pockyt 支付'][] = $s;
    } elseif (strpos($key, 'payment_payssion_') === 0) {
        $grouped['Payssion 支付'][] = $s;
    } elseif (strpos($key, 'payment_') === 0) {
        $grouped['支付设置'][] = $s;
    } else {
        $grouped['通用设置'][] = $s;
    }
}
?>
<div class="topbar">
    <h1><i class="bi bi-gear"></i> 系统设置</h1>
</div>

<form onsubmit="return saveSettings(event)">
    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
    
    <?php foreach ($grouped as $groupName => $groupSettings): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">
            <strong><?= htmlspecialchars($groupName) ?></strong>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:200px;">设置项</th>
                            <th>值</th>
                            <th style="width:300px;">说明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groupSettings as $setting): ?>
                        <tr>
                            <td>
                                <label class="form-label mb-0"><?= htmlspecialchars($setting['key_name']) ?></label>
                            </td>
                            <td>
                                <?php if ($setting['key_name'] === 'site_template'): ?>
                                <div class="d-flex align-items-center gap-2">
                                    <select class="form-select" name="settings[site_template]" id="siteTemplateSelect">
                                        <option value="default" <?= ($setting['value'] ?? 'default') === 'default' ? 'selected' : '' ?>>经典 · 传统后台</option>
                                        <option value="modern" <?= ($setting['value'] ?? '') === 'modern' ? 'selected' : '' ?>>现代渐变 · 紫色科技</option>
                                        <option value="dark" <?= ($setting['value'] ?? '') === 'dark' ? 'selected' : '' ?>>深色专业 · 暗黑酷炫</option>
                                        <option value="cloud" <?= ($setting['value'] ?? '') === 'cloud' ? 'selected' : '' ?>>清爽云蓝 · 明亮轻量</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary btn-sm text-nowrap" onclick="previewTemplate()"><i class="bi bi-eye"></i> 预览</button>
                                </div>
                                <?php elseif ($setting['key_name'] === 'admin_layout'): ?>
                                <select class="form-select" name="settings[admin_layout]">
                                    <option value="topbar" <?= ($setting['value'] ?? 'topbar') === 'topbar' ? 'selected' : '' ?>>顶部导航（推荐）</option>
                                    <option value="sidebar" <?= ($setting['value'] ?? '') === 'sidebar' ? 'selected' : '' ?>>左侧导航</option>
                                </select>
                                <?php elseif (in_array($setting['key_name'], ['site_announcement', 'site_notice'])): ?>
                                <textarea class="form-control" name="settings[<?= htmlspecialchars($setting['key_name']) ?>]" rows="3"><?= htmlspecialchars($setting['value'] ?? '') ?></textarea>
                                <?php elseif (in_array($setting['key_name'], ['payment_pockyt_api_key', 'payment_pockyt_secret_key', 'payment_payssion_api_key', 'payment_payssion_secret_key'])): ?>
                                <input type="password" class="form-control" name="settings[<?= htmlspecialchars($setting['key_name']) ?>]" value="<?= htmlspecialchars($setting['value'] ?? '') ?>">
                                <?php else: ?>
                                <input type="text" class="form-control" name="settings[<?= htmlspecialchars($setting['key_name']) ?>]" value="<?= htmlspecialchars($setting['value'] ?? '') ?>">
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($setting['description'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="mb-4">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> 保存设置</button>
    </div>
</form>

<div class="alert alert-info">
    <h6><i class="bi bi-palette"></i> 站点模板说明</h6>
    <hr>
    <p class="small mb-1"><strong>经典</strong> - 传统左侧导航，适合日常管理；可配合下方“后台布局”在左侧/顶部之间切换。</p>
    <p class="small mb-1"><strong>现代渐变</strong> - 蓝色渐变主色 + 顶部导航 + 大圆角卡片，适合对外展示的商城前台。</p>
    <p class="small mb-1"><strong>深色专业</strong> - 暗黑配色 + 左侧导航，适合夜间使用和追求科技感的后台。</p>
    <p class="small mb-0"><strong>清爽云蓝</strong> - 蓝天白云配色 + 顶部导航，风格偏轻量、明亮。</p>
    <p class="small text-muted mt-2 mb-0">切换模板立即对全部页面生效，保存后刷新页面即可看到效果。登录/注册页使用独立布局，不受模板影响。</p>
</div>

<div class="alert alert-info">
    <h6><i class="bi bi-info-circle"></i> 支付网关配置说明</h6>
    <hr>
    <p class="small mb-2"><strong>Pockyt</strong> - 支持支付宝、微信、USDT、PayPal</p>
    <ul class="small text-muted">
        <li>注册 <a href="https://www.pockyt.io" target="_blank">pockyt.io</a> 获取 API Key 和 Secret Key</li>
        <li>回调URL: <code><?= SITE_URL ?>/payment/notify/pockyt</code></li>
        <li>返回URL: <code><?= SITE_URL ?>/payment/return</code></li>
    </ul>
    <p class="small mb-2"><strong>Payssion</strong> - 支持支付宝、微信、PayPal、银联、FPX等</p>
    <ul class="small text-muted">
        <li>注册 <a href="https://www.payssion.com" target="_blank">payssion.com</a> 获取 API Key 和 Secret Key</li>
        <li>回调URL: <code><?= SITE_URL ?>/payment/notify/payssion</code></li>
        <li>返回URL: <code><?= SITE_URL ?>/payment/return</code></li>
    </ul>
</div>

<script>
function previewTemplate() {
    const value = document.getElementById('siteTemplateSelect').value;
    window.open('/?template_preview=' + value, '_blank');
}

async function saveSettings(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const response = await fetch('/admin/settings/save', { method: 'POST', body: formData });
    const result = await response.json();
    showToast(result.message || (result.success ? '保存成功' : '保存失败'), result.success ? 'success' : 'error');
    return false;
}
</script>
