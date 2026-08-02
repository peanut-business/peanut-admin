# LikeAdmin F02 permission restore result

- Restored role-menu rows: `5` for menu IDs `167, 169, 170, 171, 172`.
- The exact `admin_auth_url_4` file-cache path remained absent; no unrelated cache was touched.
- Reused the same server session token identity without calling the login API: length `32`, SHA-256 `b3655f62e10543a4b4679ac57bb5abf3590abf8377bd6175373fb9d0e6b3e731`.
- Browser returned to `/admin/finance/recharge_record`.
- `充值记录` and `退款记录` menus were restored.
- The restored page request to `/adminapi/recharge.recharge/lists` returned HTTP 200 and body `code=1`.
- Business fixtures, refund records/logs, user balances, and the server session snapshot were unchanged from the post-login authorized baseline.
- Screenshot: `restored.png`.
- Final database snapshot: `db-after-restore.tsv`.

