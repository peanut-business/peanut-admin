# CodeGraph 工作树策略

机器可读事实源：[resources/codegraph-registry.json](../../resources/codegraph-registry.json)

## 适用范围

CodeGraph 不是所有项目、也不是所有任务的强制工具。

Peanut Admin 在以下任务中要求先有当前 worktree 的索引：

- 跨文件调用关系和影响范围分析；
- 服务层、Module 边界和数据归属盘点；
- 架构决策、重构前依赖核对和测试影响分析；
- 将代码事实写入架构或服务登记。

纯文档、简单机械修改和已经明确文件及行号的局部查看可以不启用。其他项目是否需要
CodeGraph，由其他项目自己的事实源决定。

## 工作树规则

`.codegraph/` 是当前源码快照的可再生本地缓存，不纳入 Git。每个物理 checkout/worktree
必须有自己的索引；不能复制、软链接或把另一个 worktree 的索引当作当前快照。

新建或领取 Peanut Admin worktree 后运行：

```text
scripts/project-codegraph ensure
```

这个命令只会初始化或同步当前 worktree。需要查看所有已存在 worktree 的索引情况时运行：

```text
scripts/project-codegraph status
```

如果当前 worktree 缺索引，状态应写成“当前 worktree 缺索引”；不能写成“项目没有知识图谱”。
先查看其他 worktree，再初始化当前 worktree。索引存在但显示 Pending Changes 时，运行：

```text
scripts/project-codegraph sync
```

CodeGraph 工具不可用时，应报告工具缺失并停止依赖图谱的分析，不静默退化为“没有图谱”。
