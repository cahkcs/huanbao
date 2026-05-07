<?php

/**
 * 🌿 昆虫与环保网站 - MySQL 数据库操作类
 * 
 * 使用 PDO + MySQL
 * 管理员密码存储在数据库的 admin_users 表中
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct($config) {
        $this->config = $config;
        $this->connect();
        $this->initTables();
    }

    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                $config = require __DIR__ . '/config.php';
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * 连接MySQL数据库
     */
    private function connect() {
        $db = $this->config['database'];
        
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $db['type'],
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], $db['options']);
        } catch (PDOException $e) {
            die(json_encode([
                'success' => false,
                'error' => '数据库连接失败: ' . $e->getMessage(),
                'solution' => '请检查 api/config.php 中的 database 配置项是否正确（host, port, database, username, password）'
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 初始化数据表结构
     */
    private function initTables() {
        try {
            // 1. 管理员表（存储后台登录密码）
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_users (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    username VARCHAR(50) NOT NULL UNIQUE DEFAULT 'admin',
                    password VARCHAR(255) NOT NULL DEFAULT 'admin123',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // 初始化默认管理员账户（如果不存在）
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM admin_users");
            $stmt->execute();
            $count = $stmt->fetch()['count'];

            if ($count == 0) {
                // 从配置文件读取初始密码
                $adminUsername = $this->config['admin']['username'] ?? 'admin';
                $adminPassword = $this->config['admin']['password'] ?? 'admin123';
                
                $stmt = $this->pdo->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
                $stmt->execute([$adminUsername, $adminPassword]);
            }

            // 2. 留言表
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS messages (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    nickname VARCHAR(50) NOT NULL,
                    content TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    ip VARCHAR(45),
                    user_agent VARCHAR(200),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // 3. 访问量表
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS site_stats (
                    `key` VARCHAR(50) PRIMARY KEY,
                    value BIGINT NOT NULL DEFAULT 0,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // 初始化访问量数据（如果不存在）
            $stmt = $this->pdo->prepare("SELECT value FROM site_stats WHERE `key` = 'visit_count'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                $stmt = $this->pdo->prepare("INSERT INTO site_stats (`key`, value) VALUES ('visit_count', 0)");
                $stmt->execute();
            }

        } catch (Exception $e) {
            die(json_encode([
                'success' => false,
                'error' => '数据表初始化失败: ' . $e->getMessage(),
                'solution' => '请通过 phpMyAdmin 手动导入 api/init_database.sql 文件创建数据表'
            ], JSON_UNESCAPED_UNICODE));
        }
    }

    // ==================== 留言操作 ====================

    public function getAllMessages() {
        $stmt = $this->pdo->query("SELECT * FROM messages ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getMessagesByStatus($status) {
        $stmt = $this->pdo->prepare("SELECT * FROM messages WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public function searchMessages($searchTerm) {
        $pattern = "%{$searchTerm}%";
        $stmt = $this->pdo->prepare("
            SELECT * FROM messages 
            WHERE nickname LIKE ? OR content LIKE ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$pattern, $pattern]);
        return $stmt->fetchAll();
    }

    public function createMessage($nickname, $content, $ip = null, $userAgent = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (nickname, content, ip, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nickname, $content, $ip, $userAgent]);
        return $this->pdo->lastInsertId();
    }

    public function updateMessageStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE messages SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        return $stmt->rowCount();
    }

    public function deleteMessage($id) {
        $stmt = $this->pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function getMessageStats() {
        $stats = [];
        
        $queries = [
            'total'    => "SELECT COUNT(*) as count FROM messages",
            'pending'  => "SELECT COUNT(*) as count FROM messages WHERE status = 'pending'",
            'approved' => "SELECT COUNT(*) as count FROM messages WHERE status = 'approved'",
            'rejected' => "SELECT COUNT(*) as count FROM messages WHERE status = 'rejected'"
        ];

        foreach ($queries as $key => $sql) {
            $stmt = $this->pdo->query($sql);
            $stats[$key] = (int)$stmt->fetch()['count'];
        }

        return $stats;
    }

    // ==================== 访问量操作 ====================

    public function getVisitCount() {
        $stmt = $this->pdo->prepare("SELECT value FROM site_stats WHERE `key` = 'visit_count'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? (int)$result['value'] : 0;
    }

    public function incrementVisitCount() {
        $this->pdo->exec("UPDATE site_stats SET value = value + 1 WHERE `key` = 'visit_count'");
        return $this->getVisitCount();
    }

    public function setVisitCount($count) {
        $stmt = $this->pdo->prepare("UPDATE site_stats SET value = ? WHERE `key` = 'visit_count'");
        $stmt->execute([(int)$count]);
        return (int)$count;
    }

    // ==================== 管理员操作（密码存在数据库中）====================

    public function verifyAdmin($password) {
        $storedPassword = $this->getAdminPasswordFromDB();
        
        if ($password === $storedPassword) {
            return true;
        }
        
        return false;
    }

    public function getAdminPasswordFromDB() {
        try {
            $stmt = $this->pdo->prepare("SELECT password FROM admin_users WHERE username = 'admin' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && isset($result['password']) && !empty($result['password'])) {
                return $result['password'];
            }
        } catch (Exception $e) {
            error_log('读取管理员密码失败: ' . $e->getMessage());
        }
        
        // 如果数据库中没有或出错，返回默认值
        return 'admin123';
    }

    public function updateAdminPassword($newPassword) {
        $stmt = $this->pdo->prepare("UPDATE admin_users SET password = ?, updated_at = NOW() WHERE username = 'admin'");
        $stmt->execute([$newPassword]);
    }

    public function getPDO() {
        return $this->pdo;
    }
}
