# Peanut Admin — Agent Context

> **Read this before touching any file.** This file is the authoritative project state record.
> Last updated: 2026-08-03

---

## 1. 项目身份

| 项目 | 值 |
|------|----|
| 产品名称 | **Peanut Admin**（无 v2 后缀，永远不写 "Peanut Admin v2"） |
| 本地目录 | `/Users/xing/Documents/company-projects/peanut-admin/` |
| GitHub 仓库 | `git@github.com:peanut-business/peanut-admin.git` |
| 主分支 | `main` |

**旧路径已删除，不再存在：**
- ~~`~/Documents/company-projects/peanut-v2/`~~
- ~~`github.com:peanut-business/peanut-v2`~~
- ~~独立的 orchestration workspace `peanut-admin/`~~（旧的，非本目录）

---

## 2. 当前状态（2026-08-03）

### 2.1 LikeAdmin 1.9.4 标准版 Parity — ✅ 完成并独立验证

- 44 controllers、72 actions（≥ LikeAdmin 标准版 45/68）
- 数据库：`server/database/install.php` + `init.sql` + 23 migrations

**独立验证结果（非 Codex 自报）：**

| 验证项 | 方法 | 结果 |
|--------|------|------|
| Fresh-clone install | `git clone` → 空 MySQL → `php install.php` | 42 tables / 170 menus / 59 configs / 1 admin ✓ |
| 前端路由回归 | Playwright 1.62 headless Chromium 真实浏览器 | 30/30 routes pass ✓ |

证据文件：
- `output/playwright/v01/clone-install-summary.json` — Codex 原始报告（参考）
- `output/playwright/v02/summary.json` — **本会话独立验证，可信**
- `output/playwright/v02/*.png` — 9 组截图 + 登录截图

### 2.2 SaaS / 多租户设计 — 🗺️ Roadmap Only，未实现

设计文档位于 `docs/design/saas-roadmap/`（50 个文件）。**后端代码中没有任何多租户实现。** 不要在现有代码里查找 Tenant / pa_tenant 相关逻辑。

---

## 3. 目录结构

```
peanut-admin/
├── server/          # ThinkPHP 8 后端
│   ├── app/adminapi/    # 管理端 API（44 controllers）
│   ├── app/api/         # 前端/小程序 API
│   ├── database/
│   │   ├── install.php  # 一键安装（空库 → 全量初始化）
│   │   ├── init.sql     # 基础表 + 种子数据
│   │   └── migrations/  # 23 个增量迁移
│   └── .env             # DB/JWT 配置（不提交）
├── web/             # 管理端前端（Vue3 + ArcoDesign）
├── pc/              # PC 消费端（Nuxt3）
├── uniapp/          # 小程序/H5 客户端
├── docs/
│   ├── design/saas-roadmap/   # SaaS 路线图（roadmap only）
│   └── peanut-admin-*.md      # 开发指南、用户手册
└── output/playwright/         # 验证证据
    ├── v01/  # Codex 自报
    └── v02/  # 独立验证（可信）
```

---

## 4. 默认凭据（开发环境）

- 管理员账号：`admin` / `admin123456`
- 管理端 API：`http://127.0.0.1:8000/`（`php think run --port=8000`）
- 前端 Dev：`http://localhost:5173`（`pnpm dev`，位于 `web/`）
- API 前缀：`admin/*`（管理端），`api/*`（前端/小程序）

---

## 5. 命名规范（重要）

LikeAdmin 到 Peanut Admin 的刻意改名，**不是缺失**：

| LikeAdmin 原名 | Peanut Admin 名称 |
|----------------|-------------------|
| `user` | `member` |
| `dev_crontab` | `crontab` |
| `generate_*` | `generator_*` |
| `notice_record` | `notice_log` |
| `notice_setting` | `notice_scene` + `notice_template` |
| `user_auth` | `oauth_*` |

---

## 6. 给 Codex 的工作约定

1. 所有任务在 `/Users/xing/Documents/company-projects/peanut-admin/` 下执行
2. 功能分支命名：`feat/<描述>`，完成后 PR → main
3. 不要创建 `peanut-v2`、`v2/` 路径或任何带 "v2" 的文件名
4. SaaS 相关实现需求 → 先查阅 `docs/design/saas-roadmap/` 再动手
5. DB 变更：新建 `server/database/migrations/YYYYMMDD-<描述>.sql`，不要直接修改 `init.sql`
