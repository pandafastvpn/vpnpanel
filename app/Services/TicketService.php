<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;

/**
 * 工单服务
 */
class TicketService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 创建工单
     */
    public function createTicket($userId, $subject, $category, $priority, $content)
    {
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 200) {
            throw new \Exception('标题长度需在2-200字之间');
        }
        if (mb_strlen($content) < 5) {
            throw new \Exception('内容至少5个字');
        }

        $ticketNo = 'TK' . date('YmdHis') . str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);

        $this->db->beginTransaction();
        try {
            $ticketId = $this->db->insert('tickets', [
                'ticket_no' => $ticketNo,
                'user_id' => $userId,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => 'open',
                'last_reply_at' => date('Y-m-d H:i:s'),
                'last_reply_by' => 0,
            ]);

            $this->db->insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'user_id' => $userId,
                'is_staff' => 0,
                'content' => $content,
            ]);

            $this->db->commit();
            return ['id' => $ticketId, 'ticket_no' => $ticketNo];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 回复工单
     */
    public function replyTicket($ticketId, $userId, $content, $isStaff = false)
    {
        $ticket = $this->db->fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) {
            throw new \Exception('工单不存在');
        }
        if (mb_strlen($content) < 1) {
            throw new \Exception('回复内容不能为空');
        }
        if (!$isStaff && $ticket['user_id'] != $userId) {
            throw new \Exception('无权回复此工单');
        }

        $this->db->insert('ticket_replies', [
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'is_staff' => $isStaff ? 1 : 0,
            'content' => $content,
        ]);

        $this->db->update('tickets', [
            'status' => $isStaff ? 'replied' : 'open',
            'last_reply_at' => date('Y-m-d H:i:s'),
            'last_reply_by' => $isStaff ? 1 : 0,
        ], 'id = ?', [$ticketId]);

        return true;
    }

    /**
     * 关闭工单
     */
    public function closeTicket($ticketId, $userId)
    {
        $ticket = $this->db->fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);
        if (!$ticket) {
            throw new \Exception('工单不存在');
        }

        $this->db->update('tickets', [
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$ticketId]);

        return true;
    }

    /**
     * 获取用户工单列表
     */
    public function getUserTickets($userId, $page = 1, $perPage = 10)
    {
        $offset = ($page - 1) * $perPage;
        $tickets = $this->db->fetchAll(
            "SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT {$perPage} OFFSET {$offset}",
            [$userId]
        );
        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM tickets WHERE user_id = ?", [$userId]);

        return ['data' => $tickets, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /**
     * 获取工单详情(含回复)
     */
    public function getTicketDetail($ticketId, $userId = null, $isAdmin = false)
    {
        $ticket = $this->db->fetch("SELECT t.*, u.email FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?", [$ticketId]);
        if (!$ticket) {
            throw new \Exception('工单不存在');
        }
        if (!$isAdmin && $ticket['user_id'] != $userId) {
            throw new \Exception('无权查看此工单');
        }

        $replies = $this->db->fetchAll(
            "SELECT r.*, u.email FROM ticket_replies r LEFT JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC",
            [$ticketId]
        );

        return ['ticket' => $ticket, 'replies' => $replies];
    }

    /**
     * 获取所有工单(管理员)
     */
    public function getAllTickets($page = 1, $perPage = 20, $status = '', $category = '')
    {
        $offset = ($page - 1) * $perPage;
        $query = "SELECT t.*, u.email FROM tickets t LEFT JOIN users u ON t.user_id = u.id WHERE 1=1";
        $params = [];

        if ($status) {
            $query .= " AND t.status = ?";
            $params[] = $status;
        }
        if ($category) {
            $query .= " AND t.category = ?";
            $params[] = $category;
        }

        $countQuery = "SELECT COUNT(*) FROM tickets t WHERE 1=1";
        if ($status) $countQuery .= " AND t.status = ?";
        if ($category) $countQuery .= " AND t.category = ?";
        $total = $this->db->fetchColumn($countQuery, $params);

        $query .= " ORDER BY t.updated_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $tickets = $this->db->fetchAll($query, $params);

        return ['data' => $tickets, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
