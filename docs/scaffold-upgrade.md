# 脚手架升级执行器

`scripts/scaffold-upgrade` 为 `scripts/create-app` 创建的独立应用提供可执行的
`preflight → apply → verify → recover/rollback` 闭环。它只管理 application manifest
中标记为 `managed` 或 `generated-managed` 的文件；`app-owned` 业务、数据库、页面和
Host/override 文件永远不进入默认写集。

## 不可变版本身份

完整 release 位于 `scaffold/releases/<version>/`。manifest 固定 source commit/tree、
create-app inventory digest、逐文件模板 digest、mode、classification、policy 和 owner；
`scripts/build-scaffold-release --check` 会从固定 commit 的 Git archive 真实运行该版本的
create-app，再逐字验证完整 managed 生成树。

- `v1.0.0`：正式 create-app 最终 commit `14412607ba36f1816e39f7117f77eea4a9e7419e`，
  tree `172865d8b8057caa8a017ac591618cd914af30a5`。
- `v1.1.0`：本执行器的下一 scaffold release；精确 commit/tree 记录在其 manifest，且由
  release builder 从该固定 tree 生成。
- `v1.1.1`：修复正式 create-app 的 Plugin lock 交付边界；空 lock 不再引用 source-only
  fixture，旧 demo lifecycle runner 从 managed tree 退出。精确 commit/tree 与 managed
  digest 记录在新增 manifest，`v1.1.0` 身份保持不变。
- `v1.1.2`：create-app 采用不可变 release 身份，并把实际生成候选独立记录为
  `generation_source`；资源选择器保留由 registry 字段驱动的通用显式资源选择和模板 database
  校验，不包含 Peanut 专用资源 ID。精确 commit/tree 与 managed digest 记录在其 manifest，
  既有三代 release 身份不变。
- `v1.1.3`：修复生产管理端 Docker builder 的 Plugin lock build context；在 Vite build 前把
  应用根 `plugins.lock` 精确复制到 `/build/plugins.lock`，并把应用资源登记复制进 PHP Runtime，
  不改变 resolver 或数据库环境门禁的 fail-closed 合同。
  `v1.1.2` 与更早 release 身份保持不变。
- `v1.1.4`：增加独立 `application.version` 合同与 scaffold release v3 token。新应用默认
  `0.1.0`，所有 managed 版本表面从该值渲染；该 scaffold identity 不是 Peanut Admin
  产品 Tag/Release，且不会修改或重封 `v1.1.3`。

历史 `scaffold/legacy/brand-preflight-v1.1.0/` 只保留此前两文件 dry-run 证据。它使用旧
schema，既不是完整 release，也会被执行器 fail-closed 拒绝；没有静默覆盖历史证据。

## 命令

```bash
php scripts/scaffold-upgrade preflight \
  --project-root=/absolute/path/to/application \
  --from-manifest=/absolute/path/to/scaffold/releases/v1.1.3/scaffold-manifest.json \
  --to-manifest=/absolute/path/to/scaffold/releases/v1.1.4/scaffold-manifest.json

php scripts/scaffold-upgrade apply --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json
php scripts/scaffold-upgrade verify --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json
php scripts/scaffold-upgrade recover --project-root=/absolute/path/to/application \
  --plan=/absolute/path/to/application/.peanut/upgrades/plans/<candidate>.json
# rollback 是 recover 的等价别名
```

preflight 以 from release、当前项目和 to release 形成稳定三方 plan。managed 文件仅在项目
仍等于旧基线时替换；generated-managed 以应用 manifest 的 name/slug/package identity
确定性重放；项目单独修改的 managed 文件保留，双方修改、缺失、类型冲突和新增路径冲突
均 blocked。app-owned 文件只记录 preservation 摘要，不写入。

应用 manifest v2 的 `application.version` 是升级渲染的唯一版本输入，升级前后保持不变。
旧 v1 manifest 仅可从根 `RELEASE_METADATA.json` 唯一采用一个合法 SemVer；缺失、无效或
冲突值会在 preflight fail-closed。旧应用的 UniApp `versionName/versionCode` 与其他
app-owned 字节不会被 scaffold 升级覆盖或降级。

apply 只接受 ready、candidate 自校验通过、release checksum 未漂移、application manifest
锁和项目逐文件状态未变化的 plan。它持项目锁，在 0700 recovery 目录保存受影响路径的
存在/缺失、SHA-256、mode 和完整内容，再通过同目录 staging + 原子 rename 替换文件。
每步向 `.peanut/upgrades/ledger.ndjson` append 事件；同 candidate 已成功 apply/verify 时重复
apply 幂等。

verify 精确核对目标 template commit/tree、每个 managed 文件及 mode、managed 摘要和
app-owned pre-apply 摘要，成功后才 append `verified`。升级只替换 `template`；已有顶层
`generation_source` 保持最初生成来源不变，并照常追加 `last_scaffold_upgrade`。recover/rollback 从 recovery manifest
真实恢复旧 application manifest、文件内容、mode、原缺失/新增状态；重复恢复幂等。

父路径或目标符号链接、硬链接、路径穿越、未知 schema/policy/transform、manifest/artifact
checksum 漂移全部 fail-closed。apply 不运行数据库 migration、Plugin install、Composer/npm
依赖升级或任何服务；这些必须作为独立发布步骤执行。
