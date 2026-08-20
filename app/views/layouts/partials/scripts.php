<?php
/** 通用脚本：Bootstrap + 工具函数，供各模板布局复用 */
/** @var string $csrfToken */
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // AJAX helper
    async function apiPost(url, data) {
        const formData = new FormData();
        for (const key in data) {
            formData.append(key, data[key]);
        }
        formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= htmlspecialchars($csrfToken ?? '') ?>');
        const response = await fetch(url, { method: 'POST', body: formData });
        return await response.json();
    }
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('已复制到剪贴板');
        });
    }
    function showToast(msg, type = 'success') {
        const toast = document.createElement('div');
        toast.className = 'position-fixed top-0 end-0 m-3 p-3 rounded shadow';
        toast.style.cssText = `background:${type === 'success' ? '#10b981' : '#ef4444'};color:#fff;z-index:9999;min-width:200px;box-shadow:0 12px 32px rgba(0,0,0,.25);`;
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    // 流量格式化
    function formatTrafficBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }
    // 时长格式化
    function formatDuration(seconds) {
        if (!seconds || seconds <= 0) return '-';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return h + '小时' + m + '分';
        if (m > 0) return m + '分' + s + '秒';
        return s + '秒';
    }
</script>