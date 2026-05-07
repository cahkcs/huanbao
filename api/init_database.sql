-- ============================================
-- 🌿 昆虫与环保网站 - 数据库初始化脚本
-- ============================================
-- 
-- 使用方法：
-- 1. 打开宝塔面板的 phpMyAdmin
-- 2. 选择你的数据库（如 insect_eco）
-- 3. 点击 "导入" 标签
-- 4. 上传此文件或复制内容到 SQL 输入框
-- 5. 点击 "执行"
--
-- 执行完成后，系统就可以正常使用了！
-- 默认管理员账户：admin / admin123
-- （请登录后立即修改密码）
-- ============================================

-- 设置字符集
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. 管理员用户表（存储后台登录密码）
-- ============================================

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `username` VARCHAR(50) NOT NULL DEFAULT 'admin' COMMENT '用户名',
    `password` VARCHAR(255) NOT NULL DEFAULT 'admin123' COMMENT '登录密码',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员用户表';

-- 插入默认管理员账户
INSERT INTO `admin_users` (`username`, `password`) VALUES ('admin', 'admin123');

-- ============================================
-- 2. 留言表（存储访客留言数据）
-- ============================================

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT '主键ID',
    `nickname` VARCHAR(50) NOT NULL COMMENT '昵称',
    `content` TEXT NOT NULL COMMENT '留言内容',
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending' COMMENT '状态：待审核/已通过/已拒绝',
    `ip` VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    `user_agent` VARCHAR(200) DEFAULT NULL COMMENT '浏览器信息',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='留言表';

-- ============================================
-- 3. 站点统计表（访问量等数据）
-- ============================================

DROP TABLE IF EXISTS `site_stats`;
CREATE TABLE `site_stats` (
    `key` VARCHAR(50) NOT NULL COMMENT '配置键',
    `value` BIGINT(20) NOT NULL DEFAULT 0 COMMENT '配置值',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点统计表';

-- 初始化访问量计数器
INSERT INTO `site_stats` (`key`, `value`) VALUES ('visit_count', 0);

-- 恢复外键检查
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ✅ 完成！
-- ============================================
-- 
-- 现在你可以：
-- 1. 访问 https://你的域名/api/index.php?action=health 测试API
-- 2. 访问 https://你的域名/cahkcs/index.html 登录后台
-- 3. 使用 admin / admin123 登录
-- 4. 登录后立即修改密码！
--
