<?php
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-plus-circle"></i> 提交工单</h1>
    <a href="/tickets" class="btn btn-outline-secondary btn-sm">返回列表</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <form onsubmit="return submitTicket(event)">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
                    <div class="mb-3">
                        <label class="form-label">标题 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" required maxlength="200" 
                               placeholder="简要描述您的问题">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">分类</label>
                            <select class="form-control" name="category">
                                <option value="general">通用问题</option>
                                <option value="connection">VPN连接问题</option>
                                <option value="billing">计费/充值问题</option>
                                <option value="other">其他</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">优先级</label>
                            <select class="form-control" name="priority">
                                <option value="normal">普通</option>
                                <option value="low">低</option>
                                <option value="high">高</option>
                                <option value="urgent">紧急</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">详细描述 <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="8" required 
                                  placeholder="请详细描述您遇到的问题, 包括错误信息、操作步骤等"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="bi bi-send"></i> 提交工单
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6><i class="bi bi-info-circle text-primary"></i> 提交前请查看</h6>
                <ul class="small text-muted">
                    <li>请先查看 <a href="/tutorials">使用教程</a>, 可能已有答案</li>
                    <li>VPN连接问题请说明: 客户端类型、错误信息</li>
                    <li>计费问题请说明: 订单号、充值卡密</li>
                    <li>客服通常在24小时内回复</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
async function submitTicket(event) {
    event.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 提交中...';
    
    const formData = new FormData(event.target);
    const response = await fetch('/tickets/create', { method: 'POST', body: formData });
    const result = await response.json();
    
    if (result.success) {
        showToast('工单提交成功');
        setTimeout(() => window.location.href = result.redirect || '/tickets', 1200);
    } else {
        showToast(result.message || '提交失败', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> 提交工单';
    }
    return false;
}
</script>
