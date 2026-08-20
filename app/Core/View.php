<?php

namespace App\Core;

/**
 * 视图渲染
 */
class View
{
    private static $layout = 'layouts/app';
    private static $sections = [];

    public static function render($view, $data = [], $layout = true)
    {
        $viewFile = VIEW_PATH . '/' . $view . '.php';

        if (!file_exists($viewFile)) {
            throw new \Exception("视图文件不存在: {$view}");
        }

        $data['currentUser'] = Auth::user();
        $data['csrfToken'] = Auth::generateCsrfToken();
        $data['siteName'] = self::getSetting('site_name', SITE_NAME);
        $data['siteAnnouncement'] = self::getSetting('site_announcement', '');

        extract($data);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = null;
        if ($layout === true || $layout === null || $layout === '') {
            if (self::$layout && self::$layout !== 'layouts/app') {
                $layoutFile = VIEW_PATH . '/' . self::$layout . '.php';
            } else {
                $layoutFile = VIEW_PATH . '/' . self::resolveTemplate() . '.php';
            }
        } elseif (is_string($layout)) {
            $layoutFile = VIEW_PATH . '/' . $layout . '.php';
        }

        if ($layoutFile && file_exists($layoutFile)) {
            ob_start();
            require $layoutFile;
            return ob_get_clean();
        }

        return $content;
    }

    /**
     * 根据后台设置的站点模板解析主布局路径。
     * 模板值必须来自白名单，防止路径注入。
     */
    private static function resolveTemplate()
    {
        $template = self::getSetting('site_template', 'default');
        $preview = $_GET['template_preview'] ?? '';
        if (is_string($preview) && $preview !== '') {
            $template = $preview;
        }
        $template = preg_replace('/[^a-z0-9_-]/i', '', (string) $template);
        $map = [
            'default' => 'layouts/app',
            'modern'  => 'layouts/modern',
            'dark'    => 'layouts/dark',
            'cloud'   => 'layouts/cloud',
        ];
        return isset($map[$template]) ? $map[$template] : 'layouts/app';
    }

    public static function partial($view, $data = [])
    {
        $viewFile = VIEW_PATH . '/' . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \Exception("视图文件不存在: {$view}");
        }

        extract($data);
        ob_start();
        require $viewFile;
        return ob_get_clean();
    }

    public static function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect($url, $code = 302)
    {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    public static function setLayout($layout)
    {
        self::$layout = $layout;
    }

    public static function getSetting($key, $default = '')
    {
        try {
            $db = Database::getInstance();
            $result = $db->fetch("SELECT value FROM settings WHERE key_name = ?", [$key]);
            return $result ? $result['value'] : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}
