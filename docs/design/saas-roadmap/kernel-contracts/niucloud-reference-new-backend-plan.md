# Peanut Admin 新后端实施规划

## 基准分析：likeadmin 架构总结

### 技术栈
- ThinkPHP 8.0 + PHP 8.x
- Token：自研随机字符串 + MySQL session 表 + Redis 缓存
- 权限：RBAC（角色-菜单-管理员三表）
- 响应格式：`{code, show, msg, data}`

### 四层架构
```
Controller（接收请求，参数校验，调用 Logic）
    ↓
Logic（业务逻辑，调用 Model）
    ↓
Model（ThinkPHP Model，ORM 操作）
    ↓
Cache / Service（Redis 缓存、第三方服务）
```

### 中间件链（adminapi）
```
Init → Login → Auth
Init：校验控制器合法性，实例化控制器对象
Login：验证 token 有效性，注入 adminInfo 到 request
Auth：基于角色验证路由权限（超级管理员跳过）
```

### 数据库表（38张，la_ 前缀）
核心认证：admin、admin_role、admin_dept、admin_jobs、admin_session
权限：system_menu、system_role、system_role_menu
业务：user、dept、jobs、config、file、notice、article、crontab 等

---

## 我们的新后端规划

### 与 likeadmin 的差异点

| 项目 | likeadmin 原版 | 我们的版本 |
|---|---|---|
| Token | 自研随机字符串 + session 表 | JWT（firebase/php-jwt）|
| 表前缀 | `la_` | `pa_`（保持现有约定）|
| 多租户 | 无 | 有（后期加入）|
| PHP 版本 | 8.x | 8.3 |
| TP 版本 | 8.0.2 | 最新 8.x |

### 目录结构（直接照搬，语义完全一致）

```
app/
  adminapi/                   ← 管理后台 API
    controller/
      auth/                   ← 认证：AdminController、MenuController、RoleController
      dept/                   ← 部门
      setting/                ← 设置
        system/               ← 系统设置
        web/                  ← 网站设置
      user/                   ← 用户
      notice/                 ← 通知
      file/                   ← 文件
      crontab/                ← 定时任务
      tools/                  ← 工具（代码生成等）
    logic/                    ← 业务逻辑层（同 controller 目录结构）
    lists/                    ← 列表类（分页+搜索逻辑）
    validate/                 ← 表单验证
    service/                  ← AdminTokenService（JWT 版）
    http/
      middleware/             ← InitMiddleware、LoginMiddleware、AuthMiddleware
  api/                        ← 前台 API（C 端，后期）
  common/
    controller/               ← BaseController、BaseLikeAdminController
    model/
      auth/                   ← Admin、AdminSession、SystemMenu、SystemRole 等
      dept/                   ← Dept、Jobs
      file/                   ← File
      user/                   ← User
    logic/                    ← BaseLogic
    lists/                    ← BaseDataLists
    cache/                    ← AdminTokenCache、AdminAuthCache
    service/                  ← JsonService、FileService 等
    enum/                     ← YesNoEnum 等常量
    exception/                ← 异常处理
```

### JWT 替换方案

likeadmin 的 token 流程：
1. 登录成功 → `create_token()` 生成随机串 → 存 `admin_session` 表 → 缓存到 Redis
2. 请求时 → header 带 `token` → 从缓存取 adminInfo → 自动续期

**我们改为 JWT**：
1. 登录成功 → 生成 JWT（payload: admin_id, exp）→ 返回给前端
2. 请求时 → header 带 `Authorization: Bearer <jwt>` → 验签取 admin_id → 从 DB/缓存取 adminInfo
3. 不需要存 session 表（无状态），但保留 `admin_session` 用于多端管理和强制下线

LoginMiddleware 改动：从 `$request->header('token')` 改为解析 `Authorization: Bearer` header，其余逻辑不变。

### 响应格式（与 Arco Design Pro Vue 对齐）

likeadmin 原版：`{code: 1/0, show: 1/0, msg: '', data: {}}`
Arco Design 期望：`{code: 20000, msg: '', data: {}}`

**统一为 Arco 格式**：
```json
// 成功
{"code": 20000, "msg": "success", "data": {...}}

// 失败
{"code": 40000, "msg": "错误信息", "data": null}

// 未登录
{"code": 40100, "msg": "请先登录", "data": null}

// 无权限
{"code": 40300, "msg": "权限不足", "data": null}
```

JsonService 修改 code 映射，前端 axios 拦截器按此识别。

---

## 实施顺序

### Phase 1：骨架搭建（当前任务）
1. 新建 TP8 项目到新分支
2. 搭建目录结构
3. 配置基础中间件（Init/Login/Auth）
4. BaseController / BaseLogic / BaseModel / JsonService
5. JWT 认证（登录/刷新/退出）
6. 管理员 CRUD + 角色 + 菜单权限

### Phase 2：功能模块
- 部门/岗位管理
- 系统设置（站点信息、存储配置等）
- 文件管理
- 操作日志
- 定时任务

### Phase 3：对齐 Arco Design Pro Vue
- 确认所有 API 路径和响应格式与前端匹配
- 前端接入联调

---

## 数据库表规划（pa_ 前缀）

```sql
pa_admin              ← 管理员
pa_admin_role         ← 管理员-角色关联
pa_admin_dept         ← 管理员-部门关联
pa_admin_jobs         ← 管理员-岗位关联
pa_admin_session      ← 登录会话（用于多端管理/强制下线）
pa_system_menu        ← 菜单
pa_system_role        ← 角色
pa_system_role_menu   ← 角色-菜单关联
pa_dept               ← 部门
pa_jobs               ← 岗位
pa_config             ← 系统配置
pa_file               ← 文件
pa_operation_log      ← 操作日志
pa_dict_type          ← 字典类型
pa_dict_data          ← 字典数据
pa_notice_setting     ← 通知设置
pa_notice_record      ← 通知记录
```
