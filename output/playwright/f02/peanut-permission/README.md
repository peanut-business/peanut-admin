# Peanut F02 P2 权限撤销/恢复验收

- 角色：`role_id=9`
- 管理员：`admin_id=12`
- 浏览器会话：`peanut-f02-perm`
- 系统：仅 Peanut

## 结论

1. 授权态：充值记录、退款记录菜单可见，充值页退款按钮可见。
2. 精确删除目标关系 ID `60,61,63,64,65`，父菜单关系 ID `62` 保留。
3. 原会话刷新后 `/finance/recharge` 被移出可访问路由并进入 `not found`，页面没有充值/退款菜单或退款按钮。
4. 同一 token 请求五个已登记 API，全部返回 `40300 / 暂无访问权限`；未登记 `finance.refund/stat` 返回 `20000`。
5. 拒绝请求后订单、退款记录、退款日志、101 流水、会员金额和管理员会话摘要均未变化。
6. 五条关系按原始 `(id,role_id,menu_id)` 精确恢复；同一会话重新进入充值页，菜单及退款按钮恢复。
7. 恢复后仅复验一次 `recharge.recharge/lists`，返回 `20000`、9 条、默认 `page_size=25`。

## 关键浏览器证据

- 授权态快照：`.playwright-cli/page-2026-08-01T09-27-11-559Z.yml`
- 授权态截图：`.playwright-cli/page-2026-08-01T09-27-20-120Z.png`
- 撤权后快照：`.playwright-cli/page-2026-08-01T09-29-38-915Z.yml`
- 撤权后截图：`.playwright-cli/page-2026-08-01T09-28-39-971Z.png`
- 恢复态快照：`.playwright-cli/page-2026-08-01T09-31-39-116Z.yml`
- 恢复态截图：`.playwright-cli/page-2026-08-01T09-31-40-595Z.png`
