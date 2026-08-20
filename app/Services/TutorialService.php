<?php

namespace App\Services;

use App\Core\Database;

/**
 * 教程服务
 */
class TutorialService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listTutorials($category = '')
    {
        $query = "SELECT id, title, slug, category, sort_order, status, views, created_at FROM tutorials WHERE status = 1";
        $params = [];
        if ($category) {
            $query .= " AND category = ?";
            $params[] = $category;
        }
        $query .= " ORDER BY sort_order ASC, id ASC";
        return $this->db->fetchAll($query, $params);
    }

    public function getBySlug($slug)
    {
        $tutorial = $this->db->fetch("SELECT * FROM tutorials WHERE slug = ? AND status = 1", [$slug]);
        if ($tutorial) {
            $this->db->query("UPDATE tutorials SET views = views + 1 WHERE id = ?", [$tutorial['id']]);
        }
        return $tutorial;
    }

    public function getAllTutorials()
    {
        return $this->db->fetchAll("SELECT * FROM tutorials ORDER BY sort_order ASC, id ASC");
    }

    public function createTutorial($data)
    {
        $slug = trim($data['slug']);
        if (empty($slug)) {
            $slug = $this->generateSlug($data['title']);
        }
        $existing = $this->db->fetch("SELECT id FROM tutorials WHERE slug = ?", [$slug]);
        if ($existing) {
            throw new \Exception('URL别名已存在');
        }
        return $this->db->insert('tutorials', [
            'title' => trim($data['title']),
            'slug' => $slug,
            'category' => $data['category'] ?? 'general',
            'content' => $data['content'],
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ]);
    }

    public function updateTutorial($id, $data)
    {
        return $this->db->update('tutorials', [
            'title' => trim($data['title']),
            'slug' => trim($data['slug']),
            'category' => $data['category'] ?? 'general',
            'content' => $data['content'],
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'status' => (int)($data['status'] ?? 1),
        ], 'id = ?', [$id]);
    }

    public function deleteTutorial($id)
    {
        return $this->db->delete('tutorials', 'id = ?', [$id]);
    }

    private function generateSlug($title)
    {
        $slug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower($title));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));
        if (empty($slug)) $slug = 'tutorial-' . time();
        return $slug;
    }

    public function getCategories()
    {
        return [
            'windows' => 'Windows',
            'mac' => 'macOS',
            'ios' => 'iOS',
            'android' => 'Android',
            'router' => '路由器',
            'general' => '通用',
        ];
    }
}
