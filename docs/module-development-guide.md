# 模块开发指南

> 本指南已精简重构。详细的模块架构约定与认知模型，请参考 [用 Module 开发独立业务](plugin-module-development.md)。

## 新建模块

```bash
php think module:create <module.key>
```
该命令会生成符合最新“全栈自闭环”规范的前后端骨架。生成后，模块将物理存放于 `modules/<module.key>/` 目录下。
请注意，旧版本的 `Application/`、`Domain/` 等目录结构已被废弃，新脚手架将生成规范的 `server/controller/`、`server/service/` 等极简结构。

## 开发期工作流

1. 修改模块根目录的 `module.json` 以及资源文件（如菜单和权限定义）。
2. 在 `server/routes.php` 中声明你的路由和权限绑定。
3. 编写业务逻辑：
   - 入口出参交由 `server/controller/` 处理。
   - 核心规则交由 `server/service/` 处理，错误一律抛出 `BusinessException`。
   - 数据库交互交由继承了租户隔离基类的 `server/model/` 处理。
4. 同步并生效配置：
   ```bash
   php think module:sync --module=<module.key>
   ```

## 规范检查器

在打包或提交前，务必运行：
```bash
php think module:check <module.key>
```
命令会自动执行静态分析，验证路由映射、命名空间规范以及安全性隔离，确保你的模块符合宿主的接入要求。
