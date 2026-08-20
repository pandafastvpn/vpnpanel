<?php

namespace App\Services;

use App\Core\Database;

class AffService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getOrCreateReferralCode($userId)
    {
        $row = $this->db->fetch("SELECT * FROM aff_referral_codes WHERE user_id = ?", [$userId]);
        if ($row) {
            return $row['ref_code'];
        }
        return $this->createReferralCode($userId);
    }

    public function createReferralCode($userId)
    {
        $nextNum = (int) $this->db->fetchColumn("SELECT COALESCE(MAX(CAST(ref_code AS UNSIGNED)), 0) + 1 FROM aff_referral_codes WHERE ref_code REGEXP '^[0-9]+$'");
        if ($nextNum < 1) {
            $nextNum = 1;
        }
        $code = (string) $nextNum;
        $this->db->insert('aff_referral_codes', [
            'user_id' => $userId,
            'ref_code' => $code,
        ]);
        return $code;
    }

    public function bindReferrer($userId, $refCode)
    {
        $refCode = strtoupper(trim($refCode));
        if (empty($refCode)) {
            return false;
        }

        $referrer = $this->db->fetch(
            "SELECT user_id FROM aff_referral_codes WHERE ref_code = ?",
            [$refCode]
        );
        if (!$referrer) {
            return false;
        }

        $referrerId = (int) $referrer['user_id'];
        if ($referrerId === (int) $userId) {
            return false;
        }

        $existing = $this->db->fetch(
            "SELECT id FROM aff_invites WHERE invited_user_id = ?",
            [$userId]
        );
        if ($existing) {
            return false;
        }

        $this->db->insert('aff_invites', [
            'referrer_id' => $referrerId,
            'invited_user_id' => $userId,
            'ref_code' => $refCode,
            'status' => 'registered',
        ]);

        return true;
    }

    public function recordCommission($orderId, $userId, $amount)
    {
        $invite = $this->db->fetch(
            "SELECT * FROM aff_invites WHERE invited_user_id = ? AND status = 'registered'",
            [$userId]
        );
        if (!$invite) {
            return false;
        }

        $commissionRate = (float) $this->db->fetchColumn(
            "SELECT value FROM settings WHERE key_name = 'aff_commission_rate'"
        ) ?: 10.0;

        $commission = round($amount * $commissionRate / 100, 2);

        $this->db->insert('aff_commissions', [
            'referrer_id' => $invite['referrer_id'],
            'invited_user_id' => $userId,
            'order_id' => $orderId,
            'order_amount' => $amount,
            'commission_rate' => $commissionRate,
            'commission' => $commission,
            'status' => 'pending',
        ]);

        $this->db->update('aff_invites', [
            'status' => 'ordered',
            'order_id' => $orderId,
        ], 'id = ?', [$invite['id']]);

        return $commission;
    }

    public function getStats($userId)
    {
        $refCode = $this->getOrCreateReferralCode($userId);

        $totalInvites = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM aff_invites WHERE referrer_id = ?",
            [$userId]
        );

        $totalCommission = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE referrer_id = ?",
            [$userId]
        );

        $pendingCommission = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE referrer_id = ? AND status = 'pending'",
            [$userId]
        );

        $availableCommission = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE referrer_id = ? AND status = 'approved'",
            [$userId]
        );

        $withdrawnCommission = (float) $this->db->fetchColumn(
            "SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE referrer_id = ? AND status = 'withdrawn'",
            [$userId]
        );

        return [
            'ref_code' => $refCode,
            'ref_link' => SITE_URL . '/register?ref=' . $refCode,
            'total_invites' => $totalInvites,
            'total_commission' => $totalCommission,
            'pending_commission' => $pendingCommission,
            'available_commission' => $availableCommission,
            'withdrawn_commission' => $withdrawnCommission,
        ];
    }

    public function getInviteList($userId, $page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        $list = $this->db->fetchAll(
            "SELECT ai.*, u.email as invited_email, u.created_at as registered_at
             FROM aff_invites ai
             LEFT JOIN users u ON ai.invited_user_id = u.id
             WHERE ai.referrer_id = ?
             ORDER BY ai.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM aff_invites WHERE referrer_id = ?",
            [$userId]
        );
        return ['data' => $list, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function getCommissionList($userId, $page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        $list = $this->db->fetchAll(
            "SELECT ac.*, u.email as invited_email
             FROM aff_commissions ac
             LEFT JOIN users u ON ac.invited_user_id = u.id
             WHERE ac.referrer_id = ?
             ORDER BY ac.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM aff_commissions WHERE referrer_id = ?",
            [$userId]
        );
        return ['data' => $list, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function getWithdrawals($userId, $page = 1, $perPage = 20)
    {
        $offset = ($page - 1) * $perPage;
        $list = $this->db->fetchAll(
            "SELECT * FROM aff_withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM aff_withdrawals WHERE user_id = ?",
            [$userId]
        );
        return ['data' => $list, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    public function requestWithdrawal($userId, $amount, $method, $account)
    {
        $stats = $this->getStats($userId);
        $available = $stats['available_commission'];

        $minWithdraw = (float) $this->db->fetchColumn(
            "SELECT value FROM settings WHERE key_name = 'aff_min_withdrawal'"
        ) ?: 10.0;

        if ($amount < $minWithdraw) {
            throw new \Exception("最低提现金额为 ¥{$minWithdraw}");
        }

        if ($amount > $available) {
            throw new \Exception('可提现佣金不足');
        }

        $this->db->beginTransaction();
        try {
            $withdrawalId = $this->db->insert('aff_withdrawals', [
                'user_id' => $userId,
                'amount' => $amount,
                'method' => $method,
                'account' => $account,
                'status' => 'pending',
            ]);

            $this->db->query(
                "UPDATE aff_commissions SET status = 'locked', withdrawal_id = ? WHERE referrer_id = ? AND status = 'approved' ORDER BY created_at ASC LIMIT 999",
                [$withdrawalId, $userId]
            );

            $this->db->commit();
            return $withdrawalId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getAllStats()
    {
        $totalReferrers = (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT referrer_id) FROM aff_invites");
        $totalInvites = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM aff_invites");
        $totalCommission = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(commission), 0) FROM aff_commissions");
        $pendingCommission = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE status = 'pending'");
        $approvedCommission = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE status = 'approved'");
        $withdrawnCommission = (float) $this->db->fetchColumn("SELECT COALESCE(SUM(commission), 0) FROM aff_commissions WHERE status = 'withdrawn'");
        $pendingWithdrawals = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM aff_withdrawals WHERE status = 'pending'");

        return [
            'total_referrers' => $totalReferrers,
            'total_invites' => $totalInvites,
            'total_commission' => $totalCommission,
            'pending_commission' => $pendingCommission,
            'approved_commission' => $approvedCommission,
            'withdrawn_commission' => $withdrawnCommission,
            'pending_withdrawals' => $pendingWithdrawals,
        ];
    }

    public function approveCommissions($orderId)
    {
        if (is_array($orderId) && isset($orderId['order_id'])) {
            $orderId = $orderId['order_id'];
        }
        $orderId = (int) $orderId;
        if ($orderId > 0) {
            $this->db->update('aff_commissions', [
                'status' => 'approved',
                'approved_at' => date('Y-m-d H:i:s'),
            ], 'order_id = ? AND status = ?', [$orderId, 'pending']);
        }
    }

    public function approveCommission($commissionId)
    {
        $this->db->update('aff_commissions', [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
        ], 'id = ? AND status = ?', [(int)$commissionId, 'pending']);
    }

    public function approveWithdrawal($withdrawalId)
    {
        $w = $this->db->fetch("SELECT * FROM aff_withdrawals WHERE id = ? AND status = 'pending'", [$withdrawalId]);
        if (!$w) {
            throw new \Exception('提现记录不存在或已处理');
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('aff_commissions', [
                'status' => 'withdrawn',
            ], 'withdrawal_id = ? AND status = ?', [$withdrawalId, 'locked']);

            $this->db->update('aff_withdrawals', [
                'status' => 'approved',
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$withdrawalId]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rejectWithdrawal($withdrawalId)
    {
        $w = $this->db->fetch("SELECT * FROM aff_withdrawals WHERE id = ? AND status = 'pending'", [$withdrawalId]);
        if (!$w) {
            throw new \Exception('提现记录不存在或已处理');
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('aff_commissions', [
                'status' => 'approved',
                'withdrawal_id' => null,
            ], 'withdrawal_id = ? AND status = ?', [$withdrawalId, 'locked']);

            $this->db->update('aff_withdrawals', [
                'status' => 'rejected',
                'processed_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$withdrawalId]);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // 推荐码已改为数字递增, 不再使用随机码
}
