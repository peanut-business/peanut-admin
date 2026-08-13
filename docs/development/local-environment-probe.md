# 本地环境探针

`scripts/local-environment-probe` 对 Peanut Admin 的 development 与
local-production-preview 本地栈执行只读检查。它不会启动服务、修改数据库或输出凭据。

完整探测前，先按项目租约规则登记实际使用的固定端口和开发数据库，然后在两套本地栈
均已启动时运行：

```bash
./scripts/local-environment-probe > /tmp/peanut-admin-local-probe.json
```

JSON 报告写入标准输出，简洁摘要写入标准错误；任一检查失败时退出码为 `1`。完整模式
检查以下合同：

- 固定端口监听与 development/production-preview HTTP 入口；
- 项目登记 `resources/project-resources.json` 中唯一开发数据库的容器消费入口及
  migration ledger current；
- 两套 Nginx 的 `client_max_body_size`，两套 PHP Runtime 的
  `upload_max_filesize`、`post_max_size`、`max_file_uploads`；
- 图片 10 MiB、文件 50 MiB、视频 200 MiB 业务上限与传输层上限的大小关系；
- development 与 production-preview 的 `/storage/` 显式路由；
- 两套本地运行模式使用项目登记选择的相同数据库资源、容器入口、上传配置和固定端口合同。

若本地 env 文件不在默认的 `.local/stack.env`，可显式指定：

```bash
./scripts/local-environment-probe --env-file /absolute/path/to/stack.env
```

只验证仓库配置、不访问端口、容器或数据库时使用：

```bash
./scripts/local-environment-probe --config-only
```

config-only 报告会把运行态项目标记为 `skipped`；它不能替代完整本地环境验收。
