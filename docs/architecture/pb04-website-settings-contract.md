# PB04-02 网站设置唯一实现合同

> 状态：Implemented，待提交
>
> 应用前置提交：`20c3f9253861f51f036af6f5044fb2adb6737e95`
>
> 核心只读基线：`7fbd445d8fa547830b7782a7ac147d9ed414e0fd`
>
> 应用测试 owner：`PB04-SETTINGS-WEBSITE-001`

## 1. 决策与目标

核心 Settings 当前同时绑定 `pa_setting_*` 定义/值表、revision/ETag、平台操作员、Tenant/target scope 和 P1 audience；它不是 `pa_config` 后面的一个可替换 PDO 端口。为首片强行抽象会改变核心安全、并发和受众语义，超出网站设置范围。

本切片因此执行 PB03 的应用 owner 决策：网站配置 key、规则、`pa_config` 兼容和 ThinkPHP Host 由应用唯一拥有。核心仓不改 Runtime，不创建伪 adapter，不双写 `pa_config` 与 `pa_setting_*`。未来若单租户 Host 与核心 Settings 形成完整等价 schema，再以独立 P1 合同评估消费。

目标是把网站配置字段、默认值、校验、标准化、图片 URL 映射和原子存储收口到一个应用服务，通过一个应用存储端口使用 `pa_config`。Controller 只负责 HTTP 输入/输出，`ConfigApplicationService` 只保留现有兼容调用面。

## 2. 非目标

- 不修改 `/Users/xing/Documents/company-projects/peanut-admin-core`。
- 不修改数据库 schema、`init.sql`、migration ledger 或种子值。
- 不处理版权、协议、统计、默认头像、登录、支付、渠道或存储设置。
- 不修改管理页、路由、响应 envelope、权限字符或品牌内容。
- 不新增 Composer/npm 包、依赖、override slot、兼容字段或第二条运行路径。
- 不运行 LikeAdmin parity、全量浏览器或全仓测试。

## 3. 数据与规则

数据 owner 是应用表 `pa_config`，网站分组固定 `type=website`，唯一键是 `(type,name)`。字段固定为：

```text
name, web_favicon, web_logo, login_image, shop_name, shop_logo,
pc_logo, pc_title, pc_ico, pc_desc, pc_keywords, h5_favicon
```

`name` 与 `shop_name` 去除首尾空白后不能为空且最多 60 字符；`pc_title` 最多 120 字符；其余图片、描述和关键词字段最多 500 字符。数组、对象和资源不是字符串输入。全部字段先验证和标准化，再调用一次原子批量写；任何字段无效时不得调用存储。

图片字段读取时经 `FileService::getFileUrl`，写入时经 `FileService::setFileUrl`；非图片字段保存 trim 后的值。缺失的可选字段使用空字符串，缺失的必填字段失败。

## 4. Host 与错误边界

- HTTP 路由和权限保持 `config/website`、`config/website/save`。
- `ConfigController` 不再维护第二套网站字段规则；它把 payload 交给唯一服务，并把领域输入错误映射到现有失败 envelope。
- `ConfigApplicationService::getWebsite/saveWebsite` 保持现有静态签名，内部只装配并委托 `WebsiteConfigService`。
- `WebsiteConfigStore` 是应用内部端口，不是核心公共 API，也不是可发布包。
- `PaConfigWebsiteStore` 是唯一生产 adapter，只能读取网站分组并原子替换完整字段集合。
- 错误消息不得包含 SQL、连接信息、配置值或路径。

## 5. 精确写集

Runtime 任务只允许修改：

- `server/app/common/contract/config/WebsiteConfigStore.php`；
- `server/app/common/service/config/PaConfigWebsiteStore.php`；
- `server/app/common/service/config/WebsiteConfigService.php`；
- `server/app/adminapi/application/config/ConfigApplicationService.php`；
- `server/app/adminapi/controller/config/ConfigController.php`；
- `server/app/adminapi/validate/config/WebsiteValidate.php`；
- `server/tests/Productization/WebsiteConfigServiceTest.php`；
- `.github/workflows/ci.yml`，仅登记聚焦测试；
- 本合同、`docs/architecture/pb03-ownership-and-migration-gates.md`、`docs/productization-baseline-plan.md`、`AGENTS.md`，仅更新任务状态。

其他 Controller/Logic/Model、前端、路由、数据库、锁文件、包版本、部署和证据目录禁止修改。白名单不足时先停止并修订合同。

## 6. 测试所有权与验收

`PB04-SETTINGS-WEBSITE-001` 由 `server/tests/Productization/WebsiteConfigServiceTest.php` 拥有。测试使用内存 store 和无副作用 URL mapper，必须证明：

1. 读取只返回固定字段，缺失可选字段补默认值，图片字段通过 mapper。
2. 一次合法保存形成一个完整原子 batch，字符串被标准化，图片字段通过存储 mapper。
3. 空必填、超长和非字符串输入分别失败，store 写次数保持不变。
4. 模拟存储异常不产生服务层第二次写或静默成功。

实现 owner 只运行一次该聚焦测试、一次变更 PHP lint 和一次 `git diff --check`。真实数据库/API 的“读取、合法保存、非法不写、恢复原值”由同一 owner 在应用集成提交前执行一次；不得用纯测试宣称数据库验收已完成。

## 7. 停止线

本任务完成只证明网站设置在应用内形成唯一规则和可执行测试 owner。它不证明 PB04 全域完成，不授权核心 Settings 下游消费，不迁移其他设置，不发布包，不部署生产，也不进入 PB05。

## 8. 实施证据

- `WebsiteConfigService` 是字段、默认值、校验、标准化和图片映射的唯一实现；`ConfigApplicationService` 只装配委托。
- `WebsiteConfigStore` 与 `PaConfigWebsiteStore` 固定应用内部端口和唯一 `pa_config(type=website)` 生产 adapter。
- `ConfigController` 只映射输入错误到现有失败 envelope；`WebsiteValidate` 不再保留网站字段规则。
- `PB04-SETTINGS-WEBSITE-001` 聚焦测试通过；变更 PHP lint 通过。
- 本地开发库数据库探针一次通过：读取原值、合法保存、非法输入零写入、原值恢复与恢复后核对均成功。未运行浏览器或 parity 验收。
