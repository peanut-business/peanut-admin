# LikeAdmin F02-16 精确清理证据

执行日期：2026-08-01

## 清理范围与结果

数据库清理已在事务内按依赖顺序完成并提交，删除数量如下：

| 数据 | 删除数 |
| --- | ---: |
| `la_refund_log` | 3 |
| `la_refund_record` | 3 |
| `la_user_account_log` | 1 |
| `la_recharge_order` | 11 |
| `la_operation_log` | 130 |
| `la_admin_session` | 1 |
| `la_admin_role` | 1 |
| `la_system_role_menu` | 6 |
| `la_admin` | 1 |
| `la_system_role` | 1 |
| `la_user` | 3 |

文件与派生缓存处理：

- 已将唯一确认的 F02 导出文件移动到本证据目录：`充值记录-2026-08-01-162629.xlsx`。
- 已精确删除 F02 管理员权限缓存 `server/runtime/adminapi/cache/la/bd/ac672a233d2f93c77c4e407fe7900f.php`。
- 已精确删除当前 F02 管理员 token 缓存 `server/runtime/adminapi/cache/la/a2/eb44c0acbf43d693d3ebd0502ca4d4.php`。
- 未处理已过期且原本不存在的旧导出缓存。

## 唯一一次联合验收

数据库与文件在同一轮验收中得到以下结果：

```text
users                         0
recharge_orders               0
refund_records                0
refund_logs                   0
account_log_id10              0
operation_logs_130_scope      0
admin_session                 0
admin_role                    0
role_menu                     0
admin                         0
role                          0
admin_dept                    0
admin_jobs                    0
protect_system_menu_166_172   7
protect_operation_log_234     1
export_original_path          0
admin_auth_url_4_cache        0
current_token_cache           0
export_evidence_copy          1
```

结论：已确认的 F02 夹具全部归零；权威菜单 `la_system_menu.id=166–172` 全部保留，`la_operation_log.id=234` 保留；原导出与两个真实 `adminapi` 缓存路径均已清除，导出证据副本存在。

## 可恢复性与边界

- 导出文件只是移动到本目录，可原路恢复。
- 数据库为已提交的物理删除，只能通过数据库备份、此前夹具 SQL 或本证据记录恢复。
- 权限缓存和 token 缓存属于派生数据，可由 LikeAdmin 重建；对应管理员、会话已删除。
- 未删除 `la_system_menu` 166–172，未删除日志 id234，也未触碰其他账号、管理员、角色、日志、导出或缓存。
