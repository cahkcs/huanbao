<?php

/**
 * 🌿 昆虫与环保网站 - API 接口
 * 广东南方职业学院
 * 所有API请求都通过此文件处理
 * 访问方式：http://域名/api/index.php?action=xxx
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入必要文件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';

// 初始化数据库
$config = require __DIR__ . '/config.php';
$db = Database::getInstance($config);

// 获取请求方法和参数
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// 统一响应函数
function jsonResponse($success, $data = null, $error = null, $code = 200) {
    http_response_code($code);
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($error !== null) $response['error'] = $error;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// XSS 防护函数
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

try {
    // ==================== 路由分发 ====================

    // 首页 - 访问量统计
    if ($action === 'visit' && $method === 'GET') {
        $count = $db->incrementVisitCount();
        jsonResponse(true, ['count' => $count]);
    }

    // 更新访问量（管理员）
    if ($action === 'visit' && $method === 'PUT') {
        $count = $input['count'] ?? 0;
        
        if (!is_numeric($count) || $count < 0) {
            jsonResponse(false, null, '无效的访问量数值', 400);
        }
        
        $newCount = $db->setVisitCount((int)$count);
        jsonResponse(true, ['count' => $newCount], '访问量已更新');
    }

    // ==================== 留言板 API ====================

    // 获取留言列表
    if ($action === 'messages' && $method === 'GET') {
        $status = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        if ($search) {
            $messages = $db->searchMessages(sanitizeInput($search));
        } elseif ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $messages = $db->getMessagesByStatus($status);
        } else {
            $messages = $db->getAllMessages();
        }

        jsonResponse(true, $messages);
    }

    // 创建留言
    if ($action === 'messages' && $method === 'POST') {
        $nickname = sanitizeInput($input['nickname'] ?? '');
        $content = sanitizeInput($input['content'] ?? '');

        // 输入验证
        if (empty($nickname) || empty($content)) {
            jsonResponse(false, null, '昵称和内容不能为空', 400);
        }

        if (mb_strlen($nickname) < 2 || mb_strlen($nickname) > 20) {
            jsonResponse(false, null, '昵称长度应在2-20个字符之间', 400);
        }

        if (mb_strlen($content) < 5 || mb_strlen($content) > 500) {
            jsonResponse(false, null, '内容长度应在5-500个字符之间', 400);
        }

        // 违禁词过滤
        $bannedWords = ['敏感词1', '敏感词2', '政治', '暴力', '色情', '赌博', '毒品', '恐怖主义'];
        foreach ($bannedWords as $word) {
            if (strpos($content, $word) !== false || strpos($nickname, $word) !== false) {
                jsonResponse(false, null, '留言包含违禁内容，请修改后重试', 403);
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 200) : null;

        $id = $db->createMessage($nickname, $content, $ip, $userAgent);
        jsonResponse(true, ['id' => $id], '留言发布成功，等待管理员审核');
    }

    // 更新留言状态
    if (preg_match('/^messages\/(\d+)\/status$/', $action, $matches) && $method === 'PUT') {
        $id = (int)$matches[1];
        $status = $input['status'] ?? '';

        if (!in_array($status, ['approved', 'rejected'])) {
            jsonResponse(false, null, '无效的状态值', 400);
        }

        $db->updateMessageStatus($id, $status);
        $statusText = $status === 'approved' ? '通过' : '拒绝';
        jsonResponse(true, null, "留言已{$statusText}");
    }

    // 删除留言
    if (preg_match('/^messages\/(\d+)$/', $action, $matches) && $method === 'DELETE') {
        $id = (int)$matches[1];
        $db->deleteMessage($id);
        jsonResponse(true, null, '留言已删除');
    }

    // 获取留言统计
    if ($action === 'messages/stats' && $method === 'GET') {
        $stats = $db->getMessageStats();
        jsonResponse(true, $stats);
    }

    // ==================== 管理员 API ====================

    // 管理员登录
    if ($action === 'admin/login' && $method === 'POST') {
        $password = $input['password'] ?? '';

        if (empty($password)) {
            jsonResponse(false, null, '请输入密码', 400);
        }

        $isValid = $db->verifyAdmin($password);

        if ($isValid) {
            $token = base64_encode(date('Y-m-d H:i:s') . '-' . rand(1000, 9999));
            jsonResponse(true, [
                'token' => $token,
                'expiresIn' => 24 * 60 * 60 * 1000
            ], '登录成功');
        } else {
            jsonResponse(false, null, '密码错误', 401);
        }
    }

    // 获取访问量（管理员）
    if ($action === 'admin/visits' && $method === 'GET') {
        $count = $db->getVisitCount();
        jsonResponse(true, ['count' => $count]);
    }

    // 设置访问量（管理员）
    if ($action === 'admin/visits' && $method === 'PUT') {
        $count = $input['count'] ?? 0;

        if (!is_numeric($count) || $count < 0) {
            jsonResponse(false, null, '请输入有效的数字', 400);
        }

        $newCount = $db->setVisitCount((int)$count);
        jsonResponse(true, ['count' => $newCount], "访问量已更新为 {$newCount} 次");
    }

    // 修改管理员密码
    if ($action === 'admin/password' && $method === 'PUT') {
        $oldPassword = $input['oldPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';

        if (empty($oldPassword) || empty($newPassword)) {
            jsonResponse(false, null, '请输入旧密码和新密码', 400);
        }

        if (strlen($newPassword) < 6 || strlen($newPassword) > 50) {
            jsonResponse(false, null, '新密码长度应在6-50个字符之间', 400);
        }

        // 验证旧密码
        if (!$db->verifyAdmin($oldPassword)) {
            jsonResponse(false, null, '旧密码错误', 401);
        }

        // 更新密码
        $db->updateAdminPassword($newPassword);
        jsonResponse(true, null, '密码修改成功');
    }

    // ==================== 健康检查 ====================
    
    if ($action === 'health' && $method === 'GET') {
        jsonResponse(true, [
            'status' => 'ok',
            'timestamp' => date('c'),
            'uptime' => php_sapi_name() === 'cli' ? '' : 'running'
        ]);
    }

    // ==================== 未匹配的路由 ====================

    jsonResponse(false, null, '接口不存在或请求方法不正确', 404);

} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    jsonResponse(false, null, '服务器内部错误: ' . $e->getMessage(), 500);
}
