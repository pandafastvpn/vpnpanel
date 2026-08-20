<?php

/**
 * 路由定义
 */

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AdminController;

$router = new Router();

// 公开页面
$router->get('/', [HomeController::class, 'index']);
$router->get('/packages', [HomeController::class, 'packages']);
$router->get('/checkout/{id}', [HomeController::class, 'checkout'], ['AuthMiddleware']);
$router->post('/coupon/verify', [HomeController::class, 'verifyCoupon'], ['AuthMiddleware']);

// 认证
$router->get('/login', [HomeController::class, 'login']);
$router->post('/login', [HomeController::class, 'doLogin']);
$router->get('/register', [HomeController::class, 'register']);
$router->post('/register', [HomeController::class, 'doRegister']);
$router->get('/logout', [HomeController::class, 'logout']);

// 用户面板 (需要登录)
$router->get('/dashboard', [HomeController::class, 'dashboard'], ['AuthMiddleware']);
$router->get('/aff', [HomeController::class, 'aff'], ['AuthMiddleware']);
$router->post('/aff/withdraw', [HomeController::class, 'affWithdraw'], ['AuthMiddleware']);
$router->get('/recharge', [HomeController::class, 'recharge'], ['AuthMiddleware']);
$router->post('/recharge', [HomeController::class, 'doRecharge'], ['AuthMiddleware']);
$router->get('/orders', [HomeController::class, 'orders'], ['AuthMiddleware']);
$router->get('/vpn-account', [HomeController::class, 'vpnAccount'], ['AuthMiddleware']);
$router->post('/vpn-account/{id}/reset-password', [HomeController::class, 'resetVpnPassword'], ['AuthMiddleware']);
$router->post('/vpn-account/{id}/reset-traffic', [HomeController::class, 'resetTraffic'], ['AuthMiddleware']);
$router->post('/vpn-account/{id}/switch-subscription', [HomeController::class, 'switchSubscription'], ['AuthMiddleware']);
$router->post('/buy/{id}', [HomeController::class, 'buyPackage'], ['AuthMiddleware']);
$router->get('/profile', [HomeController::class, 'profile'], ['AuthMiddleware']);
$router->post('/profile/update', [HomeController::class, 'updateProfile'], ['AuthMiddleware']);
$router->post('/profile/change-password', [HomeController::class, 'changePassword'], ['AuthMiddleware']);

// 支付系统
$router->get('/payment/waiting', [HomeController::class, 'paymentWaiting'], ['AuthMiddleware']);
$router->get('/payment/check', [HomeController::class, 'paymentCheck'], ['AuthMiddleware']);
$router->get('/payment/return', [HomeController::class, 'paymentReturn']);
$router->post('/payment/notify/{gateway}', [HomeController::class, 'paymentNotify']);
$router->get('/payment/notify/{gateway}', [HomeController::class, 'paymentNotify']);

// 工单系统 (用户端, 需要登录)
$router->get('/tickets', [HomeController::class, 'tickets'], ['AuthMiddleware']);
$router->get('/tickets/create', [HomeController::class, 'ticketCreate'], ['AuthMiddleware']);
$router->post('/tickets/create', [HomeController::class, 'ticketStore'], ['AuthMiddleware']);
$router->get('/tickets/{id}', [HomeController::class, 'ticketDetail'], ['AuthMiddleware']);
$router->post('/tickets/{id}/reply', [HomeController::class, 'ticketReply'], ['AuthMiddleware']);
$router->post('/tickets/{id}/close', [HomeController::class, 'ticketClose'], ['AuthMiddleware']);

// 教程页面 (公开)
$router->get('/tutorials', [HomeController::class, 'tutorials']);
$router->get('/tutorials/{slug}', [HomeController::class, 'tutorialDetail']);

// 管理后台 (需要管理员)
$router->get('/admin', [AdminController::class, 'index'], ['AdminMiddleware']);
$router->get('/admin/users', [AdminController::class, 'users'], ['AdminMiddleware']);
$router->post('/admin/users/{id}/toggle', [AdminController::class, 'toggleUserStatus'], ['AdminMiddleware']);
$router->post('/admin/users/{id}/balance', [AdminController::class, 'adjustBalance'], ['AdminMiddleware']);
$router->get('/admin/packages', [AdminController::class, 'packages'], ['AdminMiddleware']);
$router->post('/admin/packages/create', [AdminController::class, 'createPackage'], ['AdminMiddleware']);
$router->post('/admin/packages/{id}/update', [AdminController::class, 'updatePackage'], ['AdminMiddleware']);
$router->post('/admin/packages/{id}/delete', [AdminController::class, 'deletePackage'], ['AdminMiddleware']);
$router->post('/admin/pricing/create', [AdminController::class, 'createPricing'], ['AdminMiddleware']);
$router->post('/admin/pricing/{id}/update', [AdminController::class, 'updatePricing'], ['AdminMiddleware']);
$router->post('/admin/pricing/{id}/delete', [AdminController::class, 'deletePricing'], ['AdminMiddleware']);
$router->get('/admin/vpn-accounts', [AdminController::class, 'vpnAccounts'], ['AdminMiddleware']);
$router->post('/admin/vpn-accounts/{id}/toggle', [AdminController::class, 'toggleVpnAccount'], ['AdminMiddleware']);
$router->post('/admin/vpn-accounts/{id}/reset-password', [AdminController::class, 'adminResetPassword'], ['AdminMiddleware']);
$router->post('/admin/vpn-accounts/{id}/reset-traffic', [AdminController::class, 'adminResetTraffic'], ['AdminMiddleware']);
$router->post('/admin/vpn-accounts/{id}/update-expire', [AdminController::class, 'adminUpdateExpireTime'], ['AdminMiddleware']);
$router->get('/admin/cards', [AdminController::class, 'cards'], ['AdminMiddleware']);
$router->post('/admin/cards/generate', [AdminController::class, 'generateCards'], ['AdminMiddleware']);
$router->get('/admin/cards/export', [AdminController::class, 'exportCards'], ['AdminMiddleware']);
$router->get('/admin/orders', [AdminController::class, 'orders'], ['AdminMiddleware']);
$router->get('/admin/settings', [AdminController::class, 'settings'], ['AdminMiddleware']);
$router->post('/admin/settings/save', [AdminController::class, 'saveSettings'], ['AdminMiddleware']);
$router->get('/admin/logs', [AdminController::class, 'logs'], ['AdminMiddleware']);

// 管理后台 - 工单管理
$router->get('/admin/tickets', [AdminController::class, 'tickets'], ['AdminMiddleware']);
$router->get('/admin/tickets/{id}', [AdminController::class, 'ticketDetail'], ['AdminMiddleware']);
$router->post('/admin/tickets/{id}/reply', [AdminController::class, 'ticketReply'], ['AdminMiddleware']);
$router->post('/admin/tickets/{id}/close', [AdminController::class, 'ticketClose'], ['AdminMiddleware']);

// 管理后台 - AFF管理
$router->get('/admin/aff', [AdminController::class, 'aff'], ['AdminMiddleware']);
$router->post('/admin/aff/commission/approve', [AdminController::class, 'affApproveCommission'], ['AdminMiddleware']);
$router->post('/admin/aff/withdrawal/approve', [AdminController::class, 'affApproveWithdrawal'], ['AdminMiddleware']);
$router->post('/admin/aff/withdrawal/reject', [AdminController::class, 'affRejectWithdrawal'], ['AdminMiddleware']);

// 管理后台 - 优惠码管理
$router->get('/admin/coupons', [AdminController::class, 'coupons'], ['AdminMiddleware']);
$router->post('/admin/coupons/create', [AdminController::class, 'createCoupon'], ['AdminMiddleware']);
$router->post('/admin/coupons/{id}/update', [AdminController::class, 'updateCoupon'], ['AdminMiddleware']);
$router->post('/admin/coupons/{id}/delete', [AdminController::class, 'deleteCoupon'], ['AdminMiddleware']);

// 管理后台 - 教程管理
$router->get('/admin/tutorials', [AdminController::class, 'tutorials'], ['AdminMiddleware']);
$router->post('/admin/tutorials/create', [AdminController::class, 'createTutorial'], ['AdminMiddleware']);
$router->post('/admin/tutorials/{id}/update', [AdminController::class, 'updateTutorial'], ['AdminMiddleware']);
$router->post('/admin/tutorials/{id}/delete', [AdminController::class, 'deleteTutorial'], ['AdminMiddleware']);

return $router;
