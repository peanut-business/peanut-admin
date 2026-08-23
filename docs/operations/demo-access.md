# Demo access handoff

账号、地址和验证日期统一维护在公开的 [Demo 登录信息](../../docs-site/demo-access.md) 页面；本文件
不再复制登录表，避免 README、运维文档和 docs-site 出现不一致。

## Resource references

- Multi-tenant candidate: `peanut-admin-production-candidate-deployment`
- Candidate domains and public Tenant credentials: `peanut-admin-production-candidate-domains`
- Standalone demo: `peanut-admin-production-deployment`
- Local single-tenant preview: `peanut-admin-local-production-preview-gateway`

资源登记中的 `.env` 密码键和 Keychain 仅是安装或交接引用，不是当前登录密码事实源。密码轮换后，
以应用数据库的真实登录结果为准，并同步更新[统一 Demo 登录信息](../../docs-site/demo-access.md)
和 `resources/project-resources.json`。
