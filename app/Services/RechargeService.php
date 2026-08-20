<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;

/**
 * 充值服务
 * 
 * 处理卡密充值和卡密生成
 */
class RechargeService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 卡密充值
     */
    public function rechargeByCard($userId, $cardNo)
    {
        $cardNo = trim($cardNo);
        if (empty($cardNo)) {
            throw new \Exception('请输入卡密');
        }

        $card = $this->db->fetch("SELECT * FROM recharge_cards WHERE card_no = ?", [$cardNo]);
        if (!$card) {
            throw new \Exception('卡密不存在');
        }

        if ($card['status'] !== 'unused') {
            throw new \Exception('卡密已使用或已失效');
        }

        if ($card['expire_at'] && strtotime($card['expire_at']) < time()) {
            $this->db->update('recharge_cards', ['status' => 'disabled'], 'id = ?', [$card['id']]);
            throw new \Exception('卡密已过期');
        }

        $this->db->beginTransaction();

        try {
            $user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);

            $orderNo = 'RC' . date('YmdHis') . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $this->db->update('users', [
                'balance' => $user['balance'] + $card['amount'],
            ], 'id = ?', [$userId]);

            $this->db->update('recharge_cards', [
                'status' => 'used',
                'used_by' => $userId,
                'used_at' => date('Y-m-d H:i:s'),
                'order_no' => $orderNo,
            ], 'id = ?', [$card['id']]);

            $this->db->insert('admin_logs', [
                'user_id' => $userId,
                'action' => 'recharge',
                'target' => $cardNo,
                'detail' => "卡密充值 {$card['amount']} 元",
                'ip' => Auth::getClientIp(),
            ]);

            $this->db->commit();

            return [
                'amount' => $card['amount'],
                'new_balance' => $user['balance'] + $card['amount'],
                'order_no' => $orderNo,
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 管理员生成卡密批次
     */
    public function generateCards($amount, $count, $expireDays = 0, $batchNo = null)
    {
        if ($count > 1000) {
            throw new \Exception('单次最多生成1000张卡密');
        }

        if (!$batchNo) {
            $batchNo = 'BAT' . date('YmdHis');
        }

        $expireAt = $expireDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$expireDays} days")) : null;

        $cards = [];
        for ($i = 0; $i < $count; $i++) {
            $cardNo = $this->generateCardNo();
            $this->db->insert('recharge_cards', [
                'card_no' => $cardNo,
                'amount' => $amount,
                'status' => 'unused',
                'batch_no' => $batchNo,
                'expire_at' => $expireAt,
            ]);
            $cards[] = $cardNo;
        }

        return [
            'batch_no' => $batchNo,
            'cards' => $cards,
            'count' => count($cards),
        ];
    }

    /**
     * 生成卡密号
     */
    private function generateCardNo()
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $cardNo = '';
            for ($i = 0; $i < 4; $i++) {
                if ($i > 0) $cardNo .= '-';
                for ($j = 0; $j < 4; $j++) {
                    $cardNo .= $chars[random_int(0, strlen($chars) - 1)];
                }
            }
        } while ($this->db->fetch("SELECT id FROM recharge_cards WHERE card_no = ?", [$cardNo]));

        return $cardNo;
    }
}
