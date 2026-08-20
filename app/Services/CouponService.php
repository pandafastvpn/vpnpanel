<?php

namespace App\Services;

use App\Core\Database;

class CouponService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function validateCoupon($code, $packageId = null, $pricingId = null, $amount = null)
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new \Exception('请输入优惠码');
        }

        $coupon = $this->db->fetch(
            "SELECT * FROM coupon_codes WHERE code = ? AND status = 1",
            [$code]
        );

        if (!$coupon) {
            throw new \Exception('优惠码不存在或已停用');
        }

        if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > time()) {
            throw new \Exception('优惠码尚未生效');
        }

        if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
            throw new \Exception('优惠码已过期');
        }

        if ($packageId && !empty($coupon['package_id']) && (int)$coupon['package_id'] !== (int)$packageId) {
            throw new \Exception('该优惠码不适用于当前套餐');
        }

        if ($pricingId && !empty($coupon['pricing_id']) && (int)$coupon['pricing_id'] !== (int)$pricingId) {
            throw new \Exception('该优惠码不适用于当前定价');
        }

        if ($amount !== null && $coupon['min_amount'] > 0 && $amount < $coupon['min_amount']) {
            throw new \Exception('订单金额未达到优惠码使用门槛');
        }

        $discount = 0.0;
        if ($coupon['discount_type'] === 'percent') {
            $discount = round(((float)$amount) * ((float)$coupon['discount_value'] / 100), 2);
        } else {
            $discount = round((float)$coupon['discount_value'], 2);
        }

        if ($amount !== null) {
            $discount = min($discount, (float)$amount);
        }

        return [
            'code' => $coupon['code'],
            'name' => $coupon['name'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => (float) $coupon['discount_value'],
            'discount_amount' => $discount,
            'final_amount' => $amount !== null ? round(max(0, (float)$amount - $discount), 2) : null,
        ];
    }
}
