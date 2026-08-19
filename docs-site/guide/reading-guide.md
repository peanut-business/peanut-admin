---
title: 文档阅读与命令参考
description: 用任务导向、参数表和停止线阅读 Peanut Admin 文档与脚本。
---

# 文档阅读与命令参考

这页是所有操作文档的共同说明。你不需要把一整段 shell 对话拆成自己的清单；每个命令都应先看参数表，再复制最小示例。

## 5 分钟速读

1. 先确认目标：本地开发、空库安装、生产部署，还是只做文档构建。
2. 再确认资源：环境文件、数据库资源 ID、固定端口、域名和凭据引用必须来自项目登记；不要用“试一下 localhost”来猜资源。
3. 按“前置条件 → 参数 → 命令 → 预期结果”执行。任一步结果不符，就在停止线处停下。
4. 文档中的“当前已支持”表示当前代码/证据已确认；“推荐新增”表示建议，不是现有功能。

## 状态词怎么读

| 标签 | 人话解释 | 可以做什么 | 不能推断什么 |
| --- | --- | --- | --- |
| 当前已支持 | 当前仓库有实现和对应证据 | 按文档使用该范围 | 不代表所有可选形态都已产品化 |
| 推荐新增 | 设计上值得补齐，但还没有现成实现 | 立项、写合同、排期 | 不能把它当作已有 API 或脚本 |
| 仅迁移需要 | 只为旧系统迁移保留 | 在独立迁移项目中评估 | 不应出现在 fresh Runtime/Schema |
| 暂不建议 | 当前没有足够消费者或边界 | 留在派生应用/模板 | 不应塞进核心脚手架 |
| 待核验 | 事实证据还不完整 | 先补证据再决策 | 不能宣称兼容或可上线 |

## 常用脚本参数表

### 本地服务：`scripts/local-stack.sh`

| 参数/命令 | 必填 | 默认值 | 作用 | 风险/停止线 |
| --- | --- | --- | --- | --- |
| `dev-up` | 是 | — | 启动登记的开发 API、前端和网关 | 资源 lease 不一致或端口已被占用时停止，不换随机端口 |
| `status` | 是（诊断时） | — | 查看进程、容器和健康状态 | 只读；不等于业务登录成功 |
| `dev-down` | 是（结束时） | — | 停止本次开发栈 | 先确认 owner/lease，避免停止别人的服务 |
| `PEANUT_LOCAL_ENV_FILE` | 否 | `.local/stack.env` | 指定本 worktree 的端口和宿主 API | 只使用已登记端口；不要指向生产环境文件 |

最小示例：

```bash
./scripts/local-stack.sh dev-up
./scripts/local-stack.sh status
```

预期结果：`status` 显示登记的 PHP/API、Web 和网关均为 running/healthy，浏览器可打开登记的 `/admin/` 地址。若只有容器启动而登录失败，转到[故障处理](/troubleshooting)。

### 空库安装：`server/database/install.php`

| 参数/环境变量 | 必填 | 默认值 | 作用 | 风险/停止线 |
| --- | --- | --- | --- | --- |
| `ADMIN_INITIAL_EMAIL` | 是 | 无 | 创建首个 Tenant owner 的邮箱 | 格式无效或与环境目标不符时停止 |
| `ADMIN_INITIAL_PASSWORD` | 是 | 无 | 创建首个 owner 的初始密码 | 仅首次安装使用；不得写入 Git、日志或截图 |
| `.env` 中的 `DB_*` | 是 | 无 | 指向已登记的空数据库 | 目标存在任何表时安装器必须拒绝，不要清库绕过 |
| `DEPLOYMENT_MODE` | 是 | 无 | `standalone` 或 `multi-tenant` | 拼写错误按 fail-closed 处理 |

```bash
export ADMIN_INITIAL_EMAIL='owner@example.com'
export ADMIN_INITIAL_PASSWORD='<至少 6 位>'
php server/database/install.php
```

预期结果：安装器创建 canonical Schema、默认 Tenant、Account/Credential/TenantMember 和首 owner；不会回显密码。然后执行 `php server/database/migrate.php --current`，只核对 checksum，不修改账本。

### 多租户本地体验：`scripts/local-multi-tenant-demo`

| 命令 | 必填 | 作用 | 结果 |
| --- | --- | --- | --- |
| `prepare` | 是 | 校验登记资源并准备私有环境 | 不创建未登记数据库 |
| `up` | 是 | 启动 Platform、公共 Admin 和 Tenant 入口 | 使用 hosts 中登记的测试域名 |
| `status` | 诊断时 | 查看固定端口和健康状态 | 不替代浏览器验收 |
| `credentials` | 体验时 | 显示合成的演示账号 | 只在本地显示，不写入仓库 |
| `down` | 结束时 | 停止本地体验栈 | 释放资源 lease |

预期结果：公共入口可以选择已加入的 Tenant，绑定域名只能进入对应 Tenant；浏览器仍需人工检查头像、文字、空白区和可点击性。

### 文档站构建

| 参数 | 必填 | 默认值 | 作用 | 风险/停止线 |
| --- | --- | --- | --- | --- |
| `PEANUT_DOCS_SITE_URL` | 否 | 空 | 构建 sitemap canonical host | 不填也可本地构建；不要写真实密钥 |
| `pnpm build` | 是 | — | 生成 `.vitepress/dist` | 仅文档检查，不触碰应用数据库或部署 |

```bash
cd docs-site
pnpm install --frozen-lockfile
PEANUT_DOCS_SITE_URL=https://docs.example.com pnpm build
```

## 统一停止线

- 资源登记缺失、目标不健康、lease 不属于当前 worktree：停止。
- 安装器发现非空库、migration checksum 不一致或 Host 与 Tenant 冲突：停止，不删除数据或放宽校验。
- 文档声称“当前已支持”但找不到代码/测试/发布证据：改标为“待核验”，不要补猜测。
- 需要修改 Runtime、Schema、线上数据或部署资源：离开本次文档工作，另开独立工作流。
