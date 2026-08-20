<?php
/** @var string $paymentNo */
/** @var string $csrfToken */
?>
<div class="topbar">
    <h1><i class="bi bi-hourglass-split"></i> 等待支付确认</h1>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4 class="mb-2">等待支付确认中...</h4>
                <p class="text-muted mb-3">请在弹出的支付页面完成支付</p>
                <p class="small text-muted">支付流水号: <code><?= htmlspecialchars($paymentNo) ?></code></p>
                <div class="progress mb-3" style="height:6px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         id="paymentProgress" style="width:0%"></div>
                </div>
                <p class="small text-muted" id="paymentStatus">正在检查支付状态...</p>
                
                <div class="mt-4 d-none" id="successBox">
                    <i class="bi bi-check-circle text-success" style="font-size:3rem"></i>
                    <h4 class="mt-2 text-success">支付成功!</h4>
                    <p>VPN账户已自动开通</p>
                    <a href="/dashboard" class="btn btn-primary mt-2">查看我的VPN</a>
                </div>
                
                <div class="mt-4 d-none" id="failBox">
                    <i class="bi bi-x-circle text-danger" style="font-size:3rem"></i>
                    <h4 class="mt-2 text-danger">支付未完成</h4>
                    <p class="text-muted">如果已完成支付, 系统会自动确认</p>
                    <a href="/packages" class="btn btn-outline-primary mt-2">返回套餐</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const paymentNo = '<?= htmlspecialchars($paymentNo) ?>';
let checkCount = 0;
const maxChecks = 60; // 最多检查60次 (5分钟)

async function checkPayment() {
    checkCount++;
    
    document.getElementById('paymentProgress').style.width = Math.min(checkCount / maxChecks * 100, 95) + '%';
    
    try {
        const response = await fetch('/payment/check?payment_no=' + paymentNo);
        const result = await response.json();
        
        if (result.status === 'paid') {
            // 支付成功
            document.querySelector('.spinner-border').classList.add('d-none');
            document.getElementById('paymentProgress').parentElement.classList.add('d-none');
            document.getElementById('paymentStatus').classList.add('d-none');
            document.getElementById('successBox').classList.remove('d-none');
            setTimeout(() => window.location.href = '/dashboard', 2000);
            return;
        }
        
        if (result.status === 'failed' || result.status === 'cancelled') {
            document.querySelector('.spinner-border').classList.add('d-none');
            document.getElementById('paymentProgress').parentElement.classList.add('d-none');
            document.getElementById('paymentStatus').classList.add('d-none');
            document.getElementById('failBox').classList.remove('d-none');
            return;
        }
        
        // 仍然等待
        document.getElementById('paymentStatus').textContent = 
            '正在检查支付状态... (第' + checkCount + '次检查)';
        
    } catch (e) {
        // 网络错误, 继续
    }
    
    if (checkCount < maxChecks) {
        setTimeout(checkPayment, 5000); // 每5秒检查一次
    } else {
        // 超时
        document.querySelector('.spinner-border').classList.add('d-none');
        document.getElementById('paymentStatus').textContent = '等待超时, 如已支付请刷新页面或联系客服';
        document.getElementById('failBox').classList.remove('d-none');
    }
}

// 开始检查
setTimeout(checkPayment, 2000);
</script>
