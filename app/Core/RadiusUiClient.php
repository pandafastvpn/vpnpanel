<?php

namespace App\Core;

/**
 * NETORA-Radius (radius-ui) API 客户端。
 *
 * 项目地址: https://github.com/desienkz-slp/radius-ui
 * API 使用 Bearer token，并以 RADIUS 用户名作为用户资源标识。
 */
class RadiusUiClient
{
    private $apiUrl;
    private $username;
    private $password;
    private $apiToken;
    private $profile;
    private $timeout;
    private $verifySsl;

    public function __construct($apiUrl = null, $username = null, $password = null, $apiToken = null)
    {
        $this->apiUrl = rtrim($apiUrl ?: RADIUS_API_URL, '/');
        $this->username = $username ?: (defined('RADIUS_API_USER') ? RADIUS_API_USER : '');
        $this->password = $password ?: (defined('RADIUS_API_PASS') ? RADIUS_API_PASS : '');
        $this->apiToken = $apiToken ?: (defined('RADIUS_API_TOKEN') ? RADIUS_API_TOKEN : '');
        $this->profile = defined('RADIUS_PROFILE') ? RADIUS_PROFILE : '';
        $this->timeout = defined('RADIUS_API_TIMEOUT') ? (int) RADIUS_API_TIMEOUT : 30;
        $this->verifySsl = defined('RADIUS_API_VERIFY_SSL') ? (bool) RADIUS_API_VERIFY_SSL : true;
    }

    public function login()
    {
        $response = $this->httpRequest('POST', '/api/auth', [
            'username' => $this->username,
            'password' => $this->password,
        ], false);

        if (empty($response['api_token'])) {
            throw new \Exception('NETORA-Radius 登录失败: ' . $this->errorMessage($response));
        }

        $this->apiToken = $response['api_token'];
        return $this->apiToken;
    }

    private function ensureToken()
    {
        if (!$this->apiToken) {
            if (!$this->username || !$this->password) {
                throw new \Exception('NETORA-Radius 未配置 API Token 或管理员账号密码');
            }
            $this->login();
        }
    }

    private function httpRequest($method, $path, $data = null, $auth = true)
    {
        if (!function_exists('curl_init')) {
            throw new \Exception('PHP cURL 扩展未安装');
        }

        if ($auth) {
            $this->ensureToken();
        }

        $ch = curl_init($this->apiUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer ' . $this->apiToken;
        }
        if ($data !== null) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                curl_close($ch);
                throw new \Exception('NETORA-Radius 请求数据编码失败');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("NETORA-Radius API 请求失败 [{$method} {$path}]: {$error}");
        }
        curl_close($ch);

        $response = json_decode($body, true);
        if (!is_array($response)) {
            throw new \Exception("NETORA-Radius API 响应无效 [{$method} {$path}] HTTP {$status}");
        }
        if ($status < 200 || $status >= 300 || isset($response['error'])) {
            throw new \Exception("NETORA-Radius API 错误 [{$method} {$path}] HTTP {$status}: " . $this->errorMessage($response));
        }

        return $response;
    }

    private function errorMessage($response)
    {
        return $response['error'] ?? $response['message'] ?? '未知错误';
    }

    private function resolveProfile($profile)
    {
        if (is_string($profile) && $profile !== '' && !ctype_digit($profile)) {
            return $profile;
        }
        if ($this->profile !== '') {
            return $this->profile;
        }
        throw new \Exception('请在 config.php 中设置 RADIUS_PROFILE（NETORA-Radius Profile 名称）');
    }

    public function createUser($username, $password, $profile = null, $options = [])
    {
        $payload = [
            'username' => $username,
            'password' => $password,
            'profile' => $this->resolveProfile($profile),
        ];
        if (!empty($options['nas_ip'])) {
            $payload['nas_ip'] = $options['nas_ip'];
        }
        $this->httpRequest('POST', '/api/users', $payload);

        if (($options['status'] ?? 'enabled') !== 'enabled') {
            $this->disableUser($username);
        }

        return ['id' => $username, 'username' => $username];
    }

    public function updateUser($username, $data)
    {
        // radius-ui 的 PUT 接口会无条件写入密码字段；只更新 Profile 时必须
        // 带上当前密码，否则服务端会尝试把 NULL 写入 radcheck.value。
        $payload = [];
        if (array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '') {
            $payload['password'] = $data['password'];
        }
        if (array_key_exists('profile', $data) && $data['profile'] !== null && $data['profile'] !== '') {
            $payload['profile'] = $this->resolveProfile($data['profile']);
        }
        if (array_key_exists('nas_ip', $data) && $data['nas_ip'] !== null && $data['nas_ip'] !== '') {
            $payload['nas_ip'] = $data['nas_ip'];
        }

        if (isset($payload['profile']) && !isset($payload['password'])) {
            $user = $this->getUser($username);
            $payload['password'] = $user['password'] ?? null;
            if ($payload['password'] === null || $payload['password'] === '') {
                throw new \Exception('无法读取 NETORA-Radius 用户当前密码，不能安全更新 Profile');
            }
        }

        if ($payload) {
            $this->httpRequest('PUT', '/api/users/' . rawurlencode($username), $payload);
        }
        if (isset($data['status']) && $data['status'] !== 'enabled') {
            $this->disableUser($username);
        }

        return ['username' => $username];
    }

    public function deleteUser($username)
    {
        $this->httpRequest('DELETE', '/api/users/' . rawurlencode($username));
        return true;
    }

    public function listUsers($page = 1, $perPage = 20, $filters = [])
    {
        $users = $this->httpRequest('GET', '/api/users');
        if (!empty($filters['username'])) {
            $needle = (string) $filters['username'];
            $users = array_values(array_filter($users, function ($user) use ($needle) {
                return isset($user['username']) && $user['username'] === $needle;
            }));
        }
        $offset = max(0, ((int) $page - 1) * (int) $perPage);
        return ['data' => array_slice($users, $offset, (int) $perPage), 'total' => count($users)];
    }

    public function getUser($username)
    {
        $user = $this->findUserByUsername($username);
        if (!$user) {
            throw new \Exception('NETORA-Radius 用户不存在');
        }
        return $user;
    }

    public function findUserByUsername($username)
    {
        $result = $this->listUsers(1, 1, ['username' => $username]);
        return $result['data'][0] ?? null;
    }

    public function disableUser($username)
    {
        return $this->httpRequest('POST', '/api/users/' . rawurlencode($username) . '/disable');
    }

    public function enableUser($username, $password = null, $profile = null)
    {
        $payload = [];
        if ($password !== null && $password !== '') {
            $payload['password'] = $password;
        }
        if ($profile !== null && $profile !== '') {
            $payload['profile'] = $profile;
        } elseif ($this->profile !== '') {
            $payload['profile'] = $this->profile;
        }
        return $this->httpRequest('POST', '/api/users/' . rawurlencode($username) . '/enable', $payload);
    }

    public function updateUserExpire($username, $expireTime)
    {
        return ['username' => $username, 'expire_time' => $expireTime];
    }

    public function updatePassword($username, $password)
    {
        return $this->updateUser($username, ['password' => $password]);
    }

    public function listOnlineSessions($page = 1, $perPage = 20)
    {
        $sessions = $this->httpRequest('GET', '/api/sessions');
        $offset = max(0, ((int) $page - 1) * (int) $perPage);
        return ['data' => array_slice($sessions, $offset, (int) $perPage), 'total' => count($sessions)];
    }

    public function getUserSessions($username)
    {
        $sessions = $this->httpRequest('GET', '/api/sessions');
        return array_values(array_filter($sessions, function ($session) use ($username) {
            return isset($session['username']) && $session['username'] === $username;
        }));
    }

    public function getUserAccountingRecords($username, $perPage = 500, $maxPages = 1)
    {
        $records = $this->httpRequest('GET', '/api/logs/accounting');
        return array_values(array_filter($records, function ($record) use ($username) {
            return isset($record['username']) && $record['username'] === $username;
        }));
    }

    public function getUserTotalTraffic($username)
    {
        $total = 0;
        foreach ($this->getUserAccountingRecords($username) as $record) {
            $input = $record['acctinputoctets'] ?? $record['acct_input_octets'] ?? $record['input_octets'] ?? 0;
            $output = $record['acctoutputoctets'] ?? $record['acct_output_octets'] ?? $record['output_octets'] ?? 0;
            $total += max(0, (int) $input) + max(0, (int) $output);
        }
        foreach ($this->getUserSessions($username) as $session) {
            $input = $session['acctinputoctets'] ?? $session['acct_input_octets'] ?? $session['input_octets'] ?? 0;
            $output = $session['acctoutputoctets'] ?? $session['acct_output_octets'] ?? $session['output_octets'] ?? 0;
            $total += max(0, (int) $input) + max(0, (int) $output);
        }
        return $total;
    }

    public function disconnectSession($sessionId)
    {
        return $this->httpRequest('POST', '/api/sessions/kick', ['username' => $sessionId]);
    }

    public function listAccounting($page = 1, $perPage = 20, $filters = [])
    {
        $records = isset($filters['username'])
            ? $this->getUserAccountingRecords($filters['username'])
            : $this->httpRequest('GET', '/api/logs/accounting');
        $offset = max(0, ((int) $page - 1) * (int) $perPage);
        return ['data' => array_slice($records, $offset, (int) $perPage), 'total' => count($records)];
    }

    public function listProfiles($page = 1, $perPage = 100)
    {
        return $this->httpRequest('GET', '/api/profiles');
    }

    public function listNAS($page = 1, $perPage = 100)
    {
        return $this->httpRequest('GET', '/api/nas');
    }

    public function createProfile($data)
    {
        return $this->httpRequest('POST', '/api/profiles', $data);
    }

    public function getDashboard()
    {
        return $this->httpRequest('GET', '/api/system/stats');
    }
}
