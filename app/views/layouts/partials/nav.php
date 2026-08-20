<?php
/** @var array|null $currentUser 当前登录用户 */
/** @var bool $templateCompact 是否为紧凑模式 */
$templateCompact = $templateCompact ?? false;
$brand = htmlspecialchars($siteName ?? 'VPN商店');
$mini = $templateCompact ? 'nav-mini' : '';
if (!$currentUser): ?>
<div class="nav-group <?= $mini ?>">
    <div class="nav-title">导航</div>
    <a class="nav-link" href="/"><i class="bi bi-shop"></i><span>商城首页</span></a>
    <a class="nav-link" href="/packages"><i class="bi bi-box-seam"></i><span>购买套餐</span></a>
    <a class="nav-link" href="/tutorials"><i class="bi bi-book"></i><span>使用教程</span></a>
    <a class="nav-link" href="/login"><i class="bi bi-box-arrow-in-right"></i><span>登录</span></a>
    <a class="nav-link" href="/register"><i class="bi bi-person-plus"></i><span>注册</span></a>
</div>
<?php elseif (!empty($currentUser['is_admin'])): ?>
<div class="nav-group <?= $mini ?>">
    <div class="nav-title">管理后台</div>
    <a class="nav-link" href="/admin"><i class="bi bi-speedometer2"></i><span>仪表盘</span></a>
    <a class="nav-link" href="/admin/users"><i class="bi bi-people"></i><span>用户管理</span></a>
    <a class="nav-link" href="/admin/vpn-accounts"><i class="bi bi-shield-check"></i><span>VPN账户</span></a>
    <a class="nav-link" href="/admin/packages"><i class="bi bi-box-seam"></i><span>套餐管理</span></a>
    <a class="nav-link" href="/admin/orders"><i class="bi bi-receipt"></i><span>订单管理</span></a>
    <a class="nav-link" href="/admin/aff"><i class="bi bi-megaphone"></i><span>推广管理</span></a>
    <a class="nav-link" href="/admin/cards"><i class="bi bi-ticket"></i><span>卡密管理</span></a>
    <a class="nav-link" href="/admin/coupons"><i class="bi bi-tag"></i><span>优惠码</span></a>
    <a class="nav-link" href="/admin/tickets"><i class="bi bi-life-preserver"></i><span>工单管理</span></a>
    <a class="nav-link" href="/admin/tutorials"><i class="bi bi-book"></i><span>教程管理</span></a>
    <a class="nav-link" href="/admin/logs"><i class="bi bi-journal-text"></i><span>操作日志</span></a>
    <a class="nav-link" href="/admin/settings"><i class="bi bi-gear"></i><span>系统设置</span></a>
</div>
<div class="nav-group <?= $mini ?>">
    <div class="nav-title">用户面板</div>
    <a class="nav-link" href="/dashboard"><i class="bi bi-house"></i><span>我的面板</span></a>
    <a class="nav-link" href="/vpn-account"><i class="bi bi-shield-lock"></i><span>VPN账户</span></a>
    <a class="nav-link" href="/orders"><i class="bi bi-clock-history"></i><span>订单记录</span></a>
    <a class="nav-link" href="/recharge"><i class="bi bi-wallet2"></i><span>充值</span></a>
    <a class="nav-link" href="/aff"><i class="bi bi-megaphone"></i><span>推广赚钱</span></a>
</div>
<?php else: ?>
<div class="nav-group <?= $mini ?>">
    <div class="nav-title">用户中心</div>
    <a class="nav-link" href="/dashboard"><i class="bi bi-house"></i><span>我的面板</span></a>
    <a class="nav-link" href="/vpn-account"><i class="bi bi-shield-lock"></i><span>VPN账户</span></a>
    <a class="nav-link" href="/packages"><i class="bi bi-box-seam"></i><span>购买套餐</span></a>
    <a class="nav-link" href="/orders"><i class="bi bi-clock-history"></i><span>订单记录</span></a>
    <a class="nav-link" href="/recharge"><i class="bi bi-wallet2"></i><span>余额充值</span></a>
    <a class="nav-link" href="/aff"><i class="bi bi-megaphone"></i><span>推广赚钱</span></a>
    <a class="nav-link" href="/tickets"><i class="bi bi-life-preserver"></i><span>我的工单</span></a>
    <a class="nav-link" href="/profile"><i class="bi bi-person"></i><span>个人设置</span></a>
</div>
<?php endif; ?>