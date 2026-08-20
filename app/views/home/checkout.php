<?php
/** @var array $package 套餐信息 */
/** @var array $pricings 定价方案列表 */
/** @var array|null $vpnAccount */
/** @var array $userAccounts 用户已有VPN账号 */
/** @var array $gateways 支付网关 */
/** @var string $csrfToken */
/** @var float $userBalance 用户余额 */
?>
<div class="topbar">
    <h1><i class="bi bi-credit-card"></i> 购买套餐</h1>
    <a href="/packages" class="btn btn-outline-secondary btn-sm">返回</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1"><?= htmlspecialchars($package['name']) ?></h4>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($package['description']) ?></p>
                    </div>
                    <?php if ($package['data_limit'] > 0): ?>
                    <span class="badge bg-info"><?= $package['data_limit'] ?>GB</span>
                    <?php else: ?>
                    <span class="badge bg-success">不限流量</span>
                    <?php endif; ?>
                </div>

                <div class="row text-center mb-4">
                    <div class="col-4 border-end">
                        <small class="text-muted d-block">下载速率</small>
                        <strong><?= round($package['down_rate'] / 1024, 1) ?> Mbps</strong>
                    </div>
                    <div class="col-4 border-end">
                        <small class="text-muted d-block">上传速率</small>
                        <strong><?= round($package['up_rate'] / 1024, 1) ?> Mbps</strong>
                    </div>
                    <div class="col-4">
                        <small class="text-muted d-block">并发连接</small>
                        <strong><?= $package['active_num'] ?></strong>
                    </div>
                </div>

                <form id="checkoutForm" onsubmit="return false;">
                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                    <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-calendar-range"></i> 选择付费周期</label>
                        <?php if (empty($pricings)): ?>
                        <div class="alert alert-warning mb-0">该套餐暂无可用定价方案, 请联系管理员。</div>
                        <?php else: ?>
                        <div class="pricing-options">
                            <?php foreach ($pricings as $i => $pricing): ?>
                            <label class="pricing-option d-flex justify-content-between align-items-center p-3 mb-2 rounded border <?= $i === 0 ? 'border-primary' : 'border-light' ?>"
                                   style="cursor:pointer; <?= $i === 0 ? 'background:rgba(99,102,241,0.05)' : 'background:#f8fafc' ?>">
                                <div class="d-flex align-items-center">
                                    <input type="radio" name="pricing_id" value="<?= $pricing['id'] ?>" class="form-check-input me-3" <?= $i === 0 ? 'checked' : '' ?> onchange="updatePrice()">
                                    <div>
                                        <strong><?= getCycleName($pricing['billing_cycle']) ?></strong>
                                        <small class="text-muted d-block">有效期 <?= $pricing['duration_days'] ?> 天</small>
                                    </div>
                                    <?php if ($pricing['is_popular']): ?>
                                    <span class="badge bg-warning text-dark ms-2">推荐</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <span class="fs-5 fw-bold text-primary" data-price="<?= $pricing['price'] ?>">¥<?= number_format($pricing['price'], 2) ?></span>
                                    <?php if ($pricing['original_price'] && $pricing['original_price'] > $pricing['price']): ?>
                                    <small class="text-muted text-decoration-line-through d-block">¥<?= number_format($pricing['original_price'], 2) ?></small>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-ticket-perforated"></i> 优惠码</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="couponCode" placeholder="输入优惠码 (可选)" style="text-transform: uppercase;">
                            <button class="btn btn-outline-primary" type="button" onclick="verifyCoupon()">
                                <i class="bi bi-check2-circle"></i> 验证
                            </button>
                        </div>
                        <small id="couponResult" class="text-muted d-block mt-1"></small>
                        <input type="hidden" id="couponDiscount" value="0">
                    </div>

                    <?php if (!empty($userAccounts)): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-shield-lock"></i> 绑定到VPN账号</label>
                        <select class="form-select" id="targetAccountSelect">
                            <option value="0">创建新子账号 (适合给其他人使用)</option>
                            <?php foreach ($userAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['username']) ?> <?= $acc['remark'] ? '(' . htmlspecialchars($acc['remark']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">选择已有账号 = 续费/追加套餐; 选择新建 = 创建独立子账号</small>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="targetAccountSelect" value="0">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-body">
                <h5 class="mb-3"><i class="bi bi-receipt"></i> 订单摘要</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">套餐</span>
                    <strong><?= htmlspecialchars($package['name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">周期</span>
                    <strong id="summaryCycle">-</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">原价</span>
                    <span id="summaryOriginal">¥0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2" id="summaryDiscountRow" style="display:none;">
                    <span class="text-success"><i class="bi bi-ticket-perforated"></i> 优惠</span>
                    <span class="text-success" id="summaryDiscount">-¥0.00</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <span class="fw-bold">应付金额</span>
                    <span class="fs-4 fw-bold text-primary" id="summaryTotal">¥0.00</span>
                </div>

                <div class="mb-3">
                    <small class="text-muted d-block mb-2">选择支付方式</small>
                    <div class="payment-methods">
                        <div class="payment-option d-flex align-items-center p-2 mb-2 rounded border <?= empty($gateways) ? 'border-primary' : 'border-light' ?>"
                             style="cursor:pointer"
                             onclick="selectPayment('balance', '', this)">
                            <i class="bi bi-wallet2 fs-5 me-2 text-primary"></i>
                            <div class="flex-grow-1">
                                <strong>余额支付</strong>
                                <small class="text-muted d-block">余额: ¥<?= number_format($userBalance, 2) ?></small>
                            </div>
                            <i class="bi bi-check-circle text-primary check-icon" style="display:none"></i>
                        </div>

                        <?php foreach ($gateways as $gw): ?>
                            <?php foreach ($gw['methods'] as $m): ?>
                            <div class="payment-option d-flex align-items-center p-2 mb-2 rounded border border-light"
                                 style="cursor:pointer"
                                 onclick="selectPayment('<?= $gw['id'] ?>', '<?= $m['id'] ?>', this)">
                                <i class="bi <?= $m['icon'] ?> fs-5 me-2"></i>
                                <div class="flex-grow-1">
                                    <strong><?= htmlspecialchars($m['name']) ?></strong>
                                    <small class="text-muted d-block">通过 <?= htmlspecialchars($gw['name']) ?></small>
                                </div>
                                <i class="bi bi-check-circle text-primary check-icon" style="display:none"></i>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button class="btn btn-primary w-100 btn-lg" id="payBtn" onclick="submitPayment()">
                    <i class="bi bi-lock"></i> 立即购买
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedPayment = {gateway: 'balance', method: ''};
let couponData = {code: '', discount: 0};
let pricingMap = {};
<?php foreach ($pricings as $p): ?>
pricingMap[<?= $p['id'] ?>] = {price: <?= $p['price'] ?>, cycle: '<?= getCycleName($p['billing_cycle']) ?>', days: <?= $p['duration_days'] ?>};
<?php endforeach; ?>

function getCycleName(cycle) {
    const map = {monthly: '月付', quarterly: '季付', yearly: '年付', weekly: '周付', biannually: '半年付'};
    return map[cycle] || cycle;
}

function updatePrice() {
    const radio = document.querySelector('input[name="pricing_id"]:checked');
    if (!radio) return;
    const pid = parseInt(radio.value);
    const info = pricingMap[pid];
    if (!info) return;

    const original = parseFloat(info.price);
    const discount = parseFloat(document.getElementById('couponDiscount').value) || 0;
    const total = Math.max(0, original - discount);

    document.getElementById('summaryCycle').textContent = info.cycle;
    document.getElementById('summaryOriginal').textContent = '¥' + original.toFixed(2);

    if (discount > 0) {
        document.getElementById('summaryDiscountRow').style.display = '';
        document.getElementById('summaryDiscount').textContent = '-¥' + discount.toFixed(2);
    } else {
        document.getElementById('summaryDiscountRow').style.display = 'none';
    }

    document.getElementById('summaryTotal').textContent = '¥' + total.toFixed(2);
}

document.querySelectorAll('input[name="pricing_id"]').forEach(r => r.addEventListener('change', updatePrice));

function selectPayment(gateway, method, element) {
    selectedPayment = {gateway, method};
    document.querySelectorAll('.check-icon').forEach(el => el.style.display = 'none');
    element.querySelector('.check-icon').style.display = 'block';
}

async function verifyCoupon() {
    const code = document.getElementById('couponCode').value.trim();
    if (!code) {
        document.getElementById('couponResult').textContent = '请输入优惠码';
        document.getElementById('couponResult').className = 'text-warning d-block mt-1';
        return;
    }

    const radio = document.querySelector('input[name="pricing_id"]:checked');
    if (!radio) {
        document.getElementById('couponResult').textContent = '请先选择付费周期';
        document.getElementById('couponResult').className = 'text-warning d-block mt-1';
        return;
    }

    const pid = parseInt(radio.value);
    const info = pricingMap[pid];

    document.getElementById('couponResult').textContent = '验证中...';
    document.getElementById('couponResult').className = 'text-muted d-block mt-1';

    try {
        const formData = new FormData();
        formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
        formData.append('code', code);
        formData.append('package_id', <?= $package['id'] ?>);
        formData.append('pricing_id', pid);
        formData.append('amount', info.price);

        const response = await fetch('/coupon/verify', {method: 'POST', body: formData});
        const result = await response.json();

        if (result.success) {
            document.getElementById('couponDiscount').value = result.discount_amount || 0;
            document.getElementById('couponResult').innerHTML = '<i class="bi bi-check-circle text-success"></i> ' + result.message;
            document.getElementById('couponResult').className = 'text-success d-block mt-1';
            couponData = {code: code, discount: result.discount_amount || 0};
            updatePrice();
        } else {
            document.getElementById('couponDiscount').value = 0;
            document.getElementById('couponResult').innerHTML = '<i class="bi bi-x-circle text-danger"></i> ' + result.message;
            document.getElementById('couponResult').className = 'text-danger d-block mt-1';
            couponData = {code: '', discount: 0};
            updatePrice();
        }
    } catch (e) {
        document.getElementById('couponResult').textContent = '验证失败, 请重试';
        document.getElementById('couponResult').className = 'text-danger d-block mt-1';
    }
}

async function submitPayment() {
    const radio = document.querySelector('input[name="pricing_id"]:checked');
    if (!radio) {
        showToast('请选择付费周期', 'error');
        return;
    }

    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 处理中...';

    const formData = new FormData();
    formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= $csrfToken ?>');
    formData.append('pricing_id', radio.value);
    formData.append('pay_method', selectedPayment.gateway);
    formData.append('gateway_method', selectedPayment.method);
    formData.append('target_account_id', document.getElementById('targetAccountSelect').value);
    if (couponData.code) {
        formData.append('coupon_code', couponData.code);
    }

    try {
        const response = await fetch('/buy/<?= $package['id'] ?>', {method: 'POST', body: formData});
        const result = await response.json();

        if (result.success) {
            if (result.pay_url) {
                showToast('正在跳转支付页面...', 'success');
                setTimeout(() => {
                    window.open(result.pay_url, '_blank');
                    window.location.href = result.redirect;
                }, 800);
            } else {
                showToast(result.message || '购买成功!', 'success');
                setTimeout(() => window.location.href = result.redirect || '/dashboard', 1200);
            }
        } else {
            showToast(result.message || '购买失败', 'error');
            if (result.redirect) {
                setTimeout(() => window.location.href = result.redirect, 1500);
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lock"></i> 立即购买';
        }
    } catch (e) {
        showToast('网络错误', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lock"></i> 立即购买';
    }
}

updatePrice();
</script>

<?php
function getCycleName($cycle) {
    $map = ['monthly' => '月付', 'quarterly' => '季付', 'yearly' => '年付', 'weekly' => '周付', 'biannually' => '半年付'];
    return $map[$cycle] ?? $cycle;
}
?>
