# 产品、Module 与实例版本

Peanut Admin 产品包含 Application、Core，以及从同一应用源码生成的 Standalone 和 Multi-tenant 两个发行版。两个 Edition 的区别是安装与租户模式，不是两个产品。

| 对象 | 版本含义 | 采用时还需记录 |
| --- | --- | --- |
| Peanut Admin 产品、Core、双 Edition | 后续统一发行使用同一个产品版本，包括预发布后缀 | 各自不可变源码和制品身份 |
| Module | 独立 Module 版本，不随产品版本自动递增 | 产品兼容范围、manifest、包摘要与发布状态 |
| 客户 Instance | 客户二次开发、配置和部署的独立实例版本 | 源产品版本、Edition、实例源码与部署回执 |

实例的 `instance_version` 与 `source_product_version` 是两个字段。例如，同一产品来源上的实例可以先后发布自己的 0.2.0、0.3.0；这不代表 Core 或产品也升级了。更新上游产品来源后，仍需完成实例自身的代码审阅、构建、验证和发布。

## 当前已发布的历史身份

Application **v3.0.14** 实际采用 Core **v0.1.0-alpha.13**。这是统一规则落地前的历史版本关系，不应改写或解释成原本同号。新同号版本必须先完成真实包发布、精确依赖采用与双 Edition 资格，不能仅修改版本数字。[Application Release](https://github.com/peanut-business/peanut-admin/releases/tag/v3.0.14) 与 [Core Release](https://github.com/peanut-opensource/peanut-admin-core/releases/tag/v0.1.0-alpha.13) 保留原有身份。

尚未完成资格的新版本不应出现在安装命令中作为可用依赖。继续使用已经发布、适合当前项目的不可变来源；统一版本规则不会自动升级既有实例。

## 发布、打包与部署分别判断

源码中存在 Module、本地生成 Module 包、完成独立发布，以及生产实例已采用，是不同状态。Core 包发布也不代表应用或实例完成部署；在线演示的版本以其单独说明为准。

创建和交付步骤见[应用与 Module 生命周期](application-module-lifecycle.md)，实例升级见[部署与升级](deployment-upgrade.md)，已发布源码见[版本与发布](../releases.md)，运行演示见[在线演示](../demo-access.md)。
