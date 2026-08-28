# Demo access handoff

仓库不维护公开或可复用的 Demo 登录表。账号、地址和验证日期只能由获授权的部署 owner 在
对应运行资源中维护；本文件不复制凭据或外部入口。

## Resource references

- Multi-tenant candidate: `peanut-admin-production-candidate-deployment`
- Candidate domains and public Tenant credentials: `peanut-admin-production-candidate-domains`
- Standalone demo: `peanut-admin-production-deployment`
- Local single-tenant preview: `peanut-admin-local-production-preview-gateway`

资源登记中的 `.env` 密码键和 Keychain 仅是安装或交接引用，不是当前登录密码事实源。密码轮换后，
以应用数据库的真实登录结果为准，并按资源登记的 owner 流程更新
`resources/project-resources.json`；不得把凭据或候选地址投影到公开站。
