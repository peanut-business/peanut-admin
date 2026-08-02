# LikeAdmin F02 P2 permission acceptance — PASS

## Identity and cache target

- Existing admin session reused without login: admin `4`, non-root, role `3`.
- Token identity was recorded only as length `32` and SHA-256 `b3655f62e10543a4b4679ac57bb5abf3590abf8377bd6175373fb9d0e6b3e731`.
- Correct cache key: `admin_auth_url_4`.
- Correct multi-app cache path: `server/runtime/adminapi/cache/la/bd/ac672a233d2f93c77c4e407fe7900f.php`.
- Cache baseline/final SHA-256: `25cf6e88ba61cc8a3e2bdaee6e198307e8fd824277d8a501e9fd3675e6f1835e`.

## Authorized baseline

- Finance menu contained `充值记录` and `退款记录`.
- Recharge rows exposed `退款` according to order/refund state.
- All three refund records exposed `退款日志`; both failed records exposed `重新退款`.
- Screenshots: `authorized-recharge.png`, `authorized-refund.png`.

## Revocation

- Deleted exactly five composite primary keys: `(3,167)`, `(3,169)`, `(3,170)`, `(3,171)`, `(3,172)`.
- Moved only the correct `runtime/adminapi` cache file to `cache-adminapi-before-revoke.php`.
- The original browser and unchanged token were retained.
- Refreshing the open refund page redirected to `/admin/403`.
- Direct navigation to `/admin/finance/recharge_record` also redirected to `/admin/403`.
- Menus and row buttons were unavailable; screenshot: `revoked-403.png`.

## Revoked same-token API results

Each endpoint below was invoked once after the correct cache invalidation.

| Method | Endpoint | HTTP | code | msg |
|---|---|---:|---:|---|
| GET | `/adminapi/recharge.recharge/lists?page_no=1&page_size=1` | 200 | 0 | `权限不足，无法访问或操作` |
| POST | `/adminapi/recharge.recharge/refund` | 200 | 0 | `权限不足，无法访问或操作` |
| POST | `/adminapi/recharge.recharge/refundAgain` | 200 | 0 | `权限不足，无法访问或操作` |
| GET | `/adminapi/finance.refund/record?page_no=1&page_size=1` | 200 | 0 | `权限不足，无法访问或操作` |
| GET | `/adminapi/finance.refund/log?record_id=1` | 200 | 0 | `权限不足，无法访问或操作` |
| GET | `/adminapi/finance.refund/stat` | 200 | 1 | empty |

The first five are registered permissions and were rejected. `finance.refund/stat` is not registered and remained allowed.

## Restore

- Reinserted all five original composite primary keys.
- Restored the exact cache bytes to the correct `runtime/adminapi` path.
- The same browser and same token returned to `/admin/finance/recharge_record`.
- Both finance menus returned.
- The restored page's `recharge.recharge/lists` response returned `code=1`, `count=9`.
- Screenshot: `restored.png`.
- `db-before.tsv` and `db-after.tsv` prove identical role relations, user balances, recharge orders, refund records/logs, and admin session identity/timestamps.

## Verdict

PASS. LikeAdmin 1.9.4 enforces registered API permissions after its actual `adminapi` authorization cache is invalidated, while unregistered endpoints are allowed. The earlier apparent API bypass was solely a wrong multi-app runtime cache path in the test procedure.

