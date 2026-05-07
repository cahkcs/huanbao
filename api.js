/**
 * API 配置文件 - PHP版前端统一接口调用
 * 
 * 📌 PHP版本说明：
 * - 所有API请求通过 /api/index.php?action=xxx 处理
 * - 无需配置端口或反向代理
 * - 上传到服务器即可运行
 */

// API 基础地址（自动检测当前域名）
const API_BASE_URL = window.location.origin;

// API 入口文件（PHP版本）
const API_ENTRY = '/api/index.php';

// ==================== API 工具函数 ====================

async function apiRequest(action, options = {}) {
    const url = `${API_BASE_URL}${API_ENTRY}?action=${action}`;
    
    try {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
            ...options.fetchOptions
        });

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || `请求失败 (HTTP ${response.status})`);
        }

        return data;
    } catch (error) {
        console.error('API请求失败:', error);
        throw error;
    }
}

// ==================== 访问量 API ====================

const VisitAPI = {
    // 获取并增加访问量（首页加载时调用）
    async increment() {
        return apiRequest('visit');
    },

    // 获取当前访问量
    async get() {
        return apiRequest('visit');
    },

    // 设置访问量（管理员）
    async set(count) {
        return apiRequest('visit', {
            method: 'PUT',
            body: { count }
        });
    }
};

// ==================== 留言板 API ====================

const MessageAPI = {
    // 获取留言列表
    async getAll(params = {}) {
        let action = 'messages';
        
        if (params.status) {
            action = `messages&status=${params.status}`;
        }
        
        if (params.search) {
            action = `messages&search=${encodeURIComponent(params.search)}`;
        }
        
        return apiRequest(action);
    },

    // 创建留言
    async create(nickname, content) {
        return apiRequest('messages', {
            method: 'POST',
            body: { nickname, content }
        });
    },

    // 更新留言状态（审核/拒绝）
    async updateStatus(id, status) {
        return apiRequest(`messages/${id}/status`, {
            method: 'PUT',
            body: { status }
        });
    },

    // 删除留言
    async delete(id) {
        return apiRequest(`messages/${id}`, {
            method: 'DELETE'
        });
    },

    // 获取统计信息
    async getStats() {
        return apiRequest('messages/stats');
    }
};

// ==================== 管理员 API ====================

const AdminAPI = {
    // 登录验证
    async login(password) {
        return apiRequest('admin/login', {
            method: 'POST',
            body: { password }
        });
    },

    // 获取访问量
    async getVisits() {
        return apiRequest('admin/visits');
    },

    // 设置访问量
    async setVisits(count) {
        return apiRequest('admin/visits', {
            method: 'PUT',
            body: { count }
        });
    },

    // 修改密码（需要旧密码验证）
    async changePassword(oldPassword, newPassword) {
        return apiRequest('admin/password', {
            method: 'PUT',
            body: { oldPassword, newPassword }
        });
    }
};

// 导出供其他脚本使用
window.API = {
    BASE_URL: API_BASE_URL,
    Visit: VisitAPI,
    Message: MessageAPI,
    Admin: AdminAPI
};
