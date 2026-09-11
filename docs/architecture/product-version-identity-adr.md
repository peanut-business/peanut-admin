# 产品、发行版、Module 与 Instance 版本身份

Document ID: `pa-docs-product-version-identity-adr`

Status: `current`（决定已接受；新同号产品发布仍须资格与 Registry 证据）

Owner: `product-architecture`

Date: 2026-09-09

## 决定

Peanut Admin 是一个产品。Application、Core PHP/Core Web 和从同一冻结 Application 源码确定性生成的 Standalone、Multi-tenant 两个 Edition，共用一个产品版本，包含相同的预发布后缀。仓库、包和 Edition 的 commit、tree、制品摘要仍分别记录；同号不能替代字节身份或资格。

Module 是独立交付单元，使用自己的 SemVer、manifest、摘要、签名与生命周期。它声明兼容的产品版本范围，不继承产品版本，也不因宿主发布自动递增。数字偶然相同不构成继承证据。

Instance 是客户基于某 Edition 二次开发、配置并部署的对象，使用独立的 `instance_version`。它记录 `source_product_version` 及精确 source release/Edition 来源。升级产品来源与发布实例是两个动作：例如产品来源 3.1.0 的实例可以发布为 0.2.0；之后实例只改业务代码，可以在同一产品来源上发布 0.3.0。实例版本不能用于选择 Core 包或上游产品 Schema 迁移。

`source_product_version`、`instance_version` 必须是独立字段，不能靠产品名、tag 前缀或一个通用 `version` 的上下文猜测。Release、Deployment、Module installation 和实例来源分别保存事实；源码 Release 不等于任何实例已部署。

## 历史解释与不可变性

已发布 Application v3.0.14 与 Core v0.1.0-alpha.13 是真实的两条历史版本序列。v3.0.14 tag 内的 `release-versions.json`、Composer/npm manifests/locks 与正式 Release 快照共同证明这一点。旧版本队列 U4 只决定 Core 与 scaffold 同号，却继续把“应用”同时用于 Peanut 产品和客户派生应用，未实施完整产品同号；Alpha.13 发布因此没有实现本 ADR 的新规则。

本 ADR supersede 该队列中“Peanut Application 产品版本与 Core 独立”的解释。客户派生应用的独立发布语义保留，并明确命名为 Instance。Alpha.13 仍是 v3.0.14 实际采用的历史 Core 版本，绝不补打 v3.0.14 Core tag 或修改既有 Release、lock、资格记录和历史 scaffold 制品来伪造一致。

下一统一产品目标为 **3.1.0**：版本身份合同发生明确变化，采用新的产品 minor 发布；这不是已发布声明。Core 内容即使没有运行时变化，也必须形成新包身份并完成相应固定资格，随后 Application 精确消费并完成双 Edition L2/P0-E。若后续 ThinkPHP Runtime 迁移改变公开合同，其发布仍使用整个产品的版本策略，不再另行建立 Core 0.2.x 产品序列。

## 发布合同与最小停止范围

1. 冻结 Core 候选，完成其现行聚合资格、固定提交审核、包内容与法律制品检查；发布新不可变 source/split/npm/Packagist 身份。
2. Application 锁定实际存在的同号 Core PHP/Web 版本及摘要，运行受影响生成、实例升级、Module 组合合同的聚焦检查。
3. 冻结同号 Application/Edition 候选，按 release control 完成完整 L2 资格；`dev → main` 经 PR，签发新 tag、Release、两个 Edition 制品。
4. 实例 owner 以自己的版本、源产品版本、Edition、不可变源码与部署回执记录采用；第三方生产实例和 Provider 仍有自己的资源与资格范围。

新 Core 包未发布只阻塞依赖它的 lock 采用和新统一 Release；资格失败只阻塞该候选及直接消费者。事实整理、公开历史纠正和独立业务开发不因此冻结。不得用旧 Alpha.13 或 v3.0.14 的通过记录冒充新版本资格。

## 事实源

新合同为根目录 `release-versions.schema.json` 定义的 `peanut.release-versions.v2`：`source_product_version`、`scaffold_template`、`core_php`、`core_web` 严格同号；产品根的 `instance_version` 为 null，客户生成物为独立 SemVer；`generated_instance_default` 仅决定新建实例的起始版本，不代表当前实例。`peanut.release-metadata.v2` 同时保存源产品和实例身份；发布 tag 使用实例版本（若有），否则使用产品版本。两个字段的消费发生在生成器、升级 Runner、安装、Platform 目标核验、发布和部署入口，不能仅靠 Schema 说明替代。

- 当前发布身份：`release-versions.json`、`RELEASE_METADATA.json`、实际包 manifests/locks、正式 Release 快照。
- 生成与升级：`VersionContract`、`ApplicationCreator`、`ApplicationReleaseVersions`、`ScaffoldUpgradeRunner` 及其实际消费者。
- Module：各 `module.json`、Plugin manifest、`plugins.lock` 与 Package/installation 账本。
- 当前交付状态与残余事项：[当前状态入口](../governance/current-state.md)；历史裁定：[主审计报告](../maintenance/fact-convergence-audit-2026-09-09.md)。

解释文档不覆盖源码事实。新字段消费、Registry 发布和最终资格未完成时，状态必须继续写为计划或部分完成。
