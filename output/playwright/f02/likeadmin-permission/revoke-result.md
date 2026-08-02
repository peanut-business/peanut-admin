# LikeAdmin F02 permission revoke result

## Mutation

- Deleted role-menu rows: `5`.
- Remaining target role-menu rows after delete: `0`.
- Invalidated only the exact `admin_auth_url_4` file-cache path by moving it to `cache-before-revoke.php`.
- The browser retained the same server session token identity: length `32`, SHA-256 `b3655f62e10543a4b4679ac57bb5abf3590abf8377bd6175373fb9d0e6b3e731`.

## UI observation

- Reloading the previously open refund page redirected to `/admin/403` with `您的账号权限不足，请联系管理员添加权限！`.
- The original navigation and row action UI was no longer accessible.
- The frontend removed its local token after the 403. For same-token API verification, the exact unchanged token was reloaded from the existing `la_admin_session` row into browser storage without calling the login API; identity was verified only by length and SHA-256.
- Screenshot: `revoked-403.png`.

## Same-token API observation

Each endpoint was invoked exactly once after revocation.

| Method | Endpoint | HTTP | code | msg | Result |
|---|---|---:|---:|---|---|
| GET | `/adminapi/recharge.recharge/lists?page_no=1&page_size=1` | 200 | 1 | empty | Unexpectedly allowed |
| POST | `/adminapi/recharge.recharge/refund` | 200 | 0 | `充值订单不存在` | Reached business validation; unexpectedly bypassed permission rejection |
| POST | `/adminapi/recharge.recharge/refundAgain` | 200 | 0 | `退款记录不存在` | Reached business validation; unexpectedly bypassed permission rejection |
| GET | `/adminapi/finance.refund/record?page_no=1&page_size=1` | 200 | 1 | empty | Unexpectedly allowed |
| GET | `/adminapi/finance.refund/log?record_id=1` | 200 | 1 | empty | Unexpectedly allowed |
| GET | `/adminapi/finance.refund/stat` | 200 | 1 | empty | Expected: endpoint is not registered in `la_system_menu` |

Expected for the first five endpoints was `code=0`, `msg=权限不足，无法访问或操作`; the expectation failed.

## One read-only diagnosis

- Database still had `0` target role-menu rows at diagnosis time.
- All five protected endpoint strings remained registered in `la_system_menu`.
- `finance.refund/stat` remained unregistered.
- The exact `admin_auth_url_4` file-cache path was absent after invalidation and API calls.
- Therefore the direct cause is not an uncommitted role-menu delete or regeneration of that exact file cache. The observed LikeAdmin runtime is bypassing or sourcing authorization outside that expected file-cache path for these API requests.

