# Demo access handoff

公开 Demo 的账号、地址和验证日期由获授权的部署 owner 在对应运行资源中维护；本文件只记录
无秘密入口和当前采用身份，不复制密码、token、Cookie 或私有凭据引用。

当前 `production-candidate` Demo 已采用正式源码 `v3.0.14` / `e30b667bbfc25d70281ddf1864b99883850afa24`，
以及仅用于演示 seed 和 v3.0.14 部署回执接线的 overlay `20a284d7bf4acc88313f29b8fe094033bf635c5c`
（archive SHA-256 `153dba557cd502faf8c6f2c74b12e42266458bba36ab50d0962b94e1f4778047`）。
宿主和运行 PHP 镜像中的 `DEPLOYMENT_RECEIPT.json` 必须逐字一致；overlay 不改变正式 Release 身份：

- Platform：<https://pa-platform.007345.xyz/platform/>
- 共享 Admin：<https://pa-admin.007345.xyz/admin/>
- Tenant A：<https://pa-tenant-a.007345.xyz/admin/>
- Tenant B：<https://pa-tenant-b.007345.xyz/admin/>
- 公开访问说明：<https://peanut-admin-doc.007345.xyz/demo-access>

## Resource references

- Multi-tenant candidate: `peanut-admin-production-candidate-deployment`
- Candidate domains and public Tenant credentials: `peanut-admin-production-candidate-domains`
- Standalone demo: `peanut-admin-production-deployment`
- Local single-tenant preview: `peanut-admin-local-production-preview-gateway`

资源登记中的 `.env` 密码键和 Keychain 仅是安装或交接引用，不是当前登录密码事实源。密码轮换后，
以应用数据库的真实登录结果为准，并按资源登记的 owner 流程更新
`resources/project-resources.json`。公开站只投影 owner 已授权的演示入口和账号；密码继续由受控交接提供。
