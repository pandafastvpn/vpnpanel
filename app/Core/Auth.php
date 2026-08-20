<?php

namespace App\Core;

/**
 * 会话和认证管理
 */
class Auth
{
    private static $user = null;

    public static function init()
    {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(SESSION_NAME);
        session_start();

        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            $user = $db->fetch(
                "SELECT * FROM users WHERE id = ? AND status = 1",
                [$_SESSION['user_id']]
            );
            if ($user) {
                self::$user = $user;
            } else {
                self::logout();
            }
        }
    }

    public static function attempt($email, $password)
    {
        $db = Database::getInstance();
        $user = $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        if ($user['status'] != 1) {
            return false;
        }

        self::$user = $user;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['is_admin'] = $user['is_admin'];

        $db->update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => self::getClientIp(),
        ], 'id = ?', [$user['id']]);

        return true;
    }

    public static function register($email, $password, $phone = null, $refCode = null)
    {
        $db = Database::getInstance();

        $existing = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            throw new \Exception('该邮箱已被注册');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $db->insert('users', [
            'email' => $email,
            'password_hash' => $hash,
            'phone' => $phone,
            'balance' => 0,
            'status' => 1,
            'is_admin' => 0,
        ]);

        $affService = new \App\Services\AffService();
        $affService->createReferralCode($userId);

        if ($refCode) {
            $affService->bindReferrer($userId, $refCode);
        }

        return $userId;
    }

    public static function user()
    {
        return self::$user;
    }

    public static function check()
    {
        return self::$user !== null;
    }

    public static function isAdmin()
    {
        return self::$user && self::$user['is_admin'] == 1;
    }

    public static function id()
    {
        return self::$user ? self::$user['id'] : null;
    }

    public static function logout()
    {
        self::$user = null;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }

            session_destroy();
        }
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => '请先登录']);
                exit;
            }
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin()
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => '无权限']);
                exit;
            }
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>您没有权限访问此页面</p>';
            exit;
        }
    }

    public static function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function getClientIp()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function generateCsrfToken()
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function verifyCsrfToken($token)
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $token)) {
            return false;
        }
        return true;
    }
}
