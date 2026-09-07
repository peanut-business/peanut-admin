# 核心代码规范与认知模型统一指南

本规范旨在消除项目从旧版本演进过程中积累的“精神分裂”与设计矛盾，确保所有开发者在开发 Peanut Admin 模块时共享同一套极简、顺滑的心智模型。

## 1. 命名空间与物理目录的统一

**核心原则：全面向 ThinkPHP 8 妥协，统一使用全小写单数目录，命名空间（Namespace）保持全小写。**

- **物理目录**：模块后端的目录一律为 `server/controller/`、`server/service/`、`server/model/`、`server/validate/`，**杜绝任何首字母大写或复数形式**。
- **命名空间**：完全与物理目录对应，放弃大写，统一使用全小写。
  - ✅ 正确：`namespace modules\official_article\controller\adminapi;`
  - ❌ 错误：`namespace Modules\OfficialArticle\Controllers\Admin;`
- **目的**：彻底消除大小写混用导致的认知内耗，避免跨平台（Mac/Linux）部署时出现的类找不到（Class not found）问题。

## 2. 业务失败处理的统一

**核心原则：Service 层一律靠“抛异常（BusinessException）”来中断流程，绝不允许返回代表错误的数组或布尔值。**

- **Service 层的写法**：
  - ✅ 正确：`if ($balance < $amount) { throw new \app\common\exception\BusinessException('余额不足'); }`
  - ❌ 错误：`if ($balance < $amount) { return ['code' => 0, 'msg' => '余额不足']; }`
- **Controller 层的写法**：
  - Controller 只需要调用 Service 并返回成功结果。异常无需手动 `try-catch`，宿主底层的全局异常接管器（ExceptionHandler）会自动将其转换为格式统一的 HTTP JSON 响应。
- **目的**：彻底终结调用者到处写 `if (!$res)` 或判断 `code == 1` 的防御性代码，让业务流向最清晰。

## 3. “RESTful 洁癖”与后台 API 的务实选择

**核心原则：在管理后台与前端交互时，放弃严格的 RESTful 动词（PUT/PATCH/DELETE），全面收敛为“两分法”。**

- **查询类接口（读取数据、列表、详情）**：一律使用 `GET`。
- **修改类接口（新增、编辑、删除、修改状态）**：一律使用 `POST`。
- **目的**：避免某些复杂 JSON 数据在特定服务器代理配置下对 `PUT/DELETE` 的拦截问题，同时减少前后端对齐接口动词的心智负担。

## 4. 前端接口 URL 与 后端物理目录的绝对映射

**核心原则：API 的 URL 必须与模块的物理路径产生 3 秒钟的直觉映射，不需要查询任何映射表。**

- **URL 规范**：`/[端]/[模块名]/[业务名词]/[动作]`
  - 示例：`/adminapi/official_article/article/add`
- **物理寻址直觉**：
  - 看到 `/adminapi/`，知道这是提供给管理端的接口，鉴权由 `adminapi` 宿主接管。
  - 看到 `official_article/`，知道这个接口在 `modules/official-article/` 这个物理目录。
  - 看到 `article/add`，马上打开 `modules/official-article/server/controller/adminapi/ArticleController.php` 寻找 `add` 方法。
- **目的**：任何前端或测试报出一个出问题的 URL，后端开发者能凭借肌肉记忆秒级定位 Controller。

---

> 记住我们的最终信条：**对插件开发者极度宽容（代码好写、结构简单、无需操心多租户），对系统底层极度严密（鉴权拦截、租户隔离全部由宿主强行接管）。**
