# 业务全流程体验审计（2026-08-21）

本报告由低成本只读审计完成，再由主代理按代码、资格脚本和资源登记做复核。审计没有启动
数据库、容器或浏览器资源，也没有把历史 `v3.0.4` 线上截图当成当前工作树的验证结果。

## 已确认事实

| 级别 | 发现 | 证据与结论 |
| --- | --- | --- |
| P0 | 现有 P0-E 浏览器组没有证明共享 Admin 的 Tenant 选择修复 | `scripts/p0e-runtime-qualification` 的 fresh fixture 给默认 Tenant 同时写入 `admin-web`/`member-api`，浏览器只使用随机默认 owner 并选择第一个 Tenant；因此 `output/p0e-p0e304/summary.json` 不能证明 Demo overlay 中 Tenant A 账号在共享 Admin Host 能选择 A/B。`seed-multi-tenant-demo.php` 的实际修复已由静态合同覆盖，但 3.0.5 发布前仍需在固定候选上做一次真实 overlay 浏览器场景。 |
| P1 | 管理员创建和登录的邮箱长度合同不一致 | 创建路径允许 Core 合同的 255 字符，登录原先只允许 50 字符。本分支已统一为有效邮箱、最多 255 字符。 |
| P1 | 普通密码的应用层前置校验比 Core 弱 | Core `PasswordHasher` 要求普通密码至少 12 位，应用安装器、部署脚本和 AdminValidate 原先写 6 位。本分支已统一为普通密码至少 12 位。 |
| P1 | 演示密码与普通密码是两种合同 | `peanut1234` 只在 `PEANUT_DEMO_MODE=enabled` 的 overlay 中接受，并以独立摘要写入；普通应用仍执行 Core 的 12 位规则。 |
| P1 | 发布链不是 tag 自动发布 | 当前没有 tag-triggered workflow；应先合入 `main`、固定候选并完成资格，再人工运行 `scripts/publish-github-release`。流程已写入源仓和 docs-site。 |
| P1 | create-app 生成的是独立应用仓库 | `create-app` 在已发布 Peanut Admin tag 上执行；生成 `.peanut/application-manifest.json`、baseline 和逐文件指纹，然后由用户 `git init`。应用不会跟随源仓 `dev/main`。 |
| P1 | 脚手架升级不等于应用升级 | `scaffold-upgrade` 只处理 `managed`/`generated-managed` 文件；Composer/npm、Plugin、数据库 migration 和服务重启必须由应用自己的发布步骤执行。3.0 跨大版本 fresh/rebuild，3.x 内 patch/minor 使用 append-only migration。 |
| P1 | fresh 部署失败时没有完整自动回滚 | `deploy-release --fresh` 有备份、确认和 recovery 指针，但删除旧 Compose/volume 后的复制、配置或启动失败不会自动恢复全部远程状态。正式生产前必须保留配对备份并准备人工 recover。 |
| P2 | Platform 邀请错误页的跨 Host 跳转仍需真实验证 | `platform/src/App.vue` 使用相对 `/admin/`；Platform 与 Tenant Admin 为不同 Host。代码路径可能在 Host policy 下拒绝，当前没有新的浏览器证据证明该分支。 |

## 本轮已落地

- 新增 3.0.5 pending candidate 的 demo 密码、邮箱校验、普通密码校验和 deploy-release 合同。
- 生成并校验 `scaffold/releases/v3.0.5/scaffold-manifest.json`，其 source/inventory/tree 摘要与候选 fixture 对齐。
- 更新 README、docs-site、create-app、scaffold-upgrade、迁移入口和独立应用生命周期说明。
- `scripts/check-stale-facts.sh` 现在会阻止当前操作文档恢复到 `peanut1234xx`、`--upgrade` 或不存在的 `migrate.php`。

## 高级模型审计建议

1. **先补 P0 资格证据，再谈发布**：把 Demo overlay 的 Tenant A 账号、共享 Admin Host、A/B Tenant 选择纳入 3.0.5 固定候选的浏览器组；必须记录选择控件出现、两个 Tenant 选项和成功进入目标 Tenant 的证据。不要用当前 7/7 历史摘要替代。
2. **发布前做一次真实凭据矩阵**：本地 demo、production-candidate Platform/Admin/Tenant A/B 和 Standalone 均使用 `peanut1234` 登录；普通 fresh 安装用 12 位以上随机密码。任何线上仍显示或接受 `peanut1234xx` 都说明候选尚未部署完成。
3. **把升级拆成可回滚步骤**：应用先更新 lock 并做 frozen install，再做 scaffold preflight/apply/verify，随后备份并运行 `install.php --migrate --target-version`，最后做 health/login/关键页面 smoke。任一步失败都保留旧应用和 recovery backup。
4. **验证 manifest 指纹**：用户克隆 starter 后应把 `.peanut/application-manifest.json` 纳入自己的仓库；升级 PR 必须同时提交 from/to manifest、plan、ledger 和 app-owned 摘要，避免只凭目录名判断来源版本。
5. **单独处理邀请跳转**：在下一次浏览器矩阵中覆盖 Platform 生成邀请、错误/过期链接和跨 Host 回到 Tenant Admin 的路径；若产品要求跨 Host，改为由 API 返回已登记 Tenant Host 的绝对 URL，而不是依赖相对路径。

## 尚未声称完成

- 3.0.5 尚未合入 `dev/main`、尚未创建 tag、尚未发布 GitHub Release，也尚未部署线上 Demo。
- 完整 L2 P0-E、线上双模式部署、真实 Demo 密码切换和独立应用升级演练均待固定 main 候选与资源租约后执行。
- 当前工作树保留此前未跟踪的 `output/p0e-p0e304/` 与 `output/playwright/production-v304-candidate/` 证据目录；它们未被本轮提交，也不代表 3.0.5 通过。
