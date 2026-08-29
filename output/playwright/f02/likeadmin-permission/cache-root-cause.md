# LikeAdmin F02 P2 API permission root cause

## Conclusion

The observed split behavior was caused by invalidating the wrong application runtime cache path. It is **not evidence that LikeAdmin 1.9.4 bypasses registered API permissions**.

The test moved:

`server/runtime/cache/la/bd/ac672a233d2f93c77c4e407fe7900f.php`

The HTTP requests run inside the ThinkPHP multi-application `adminapi` application, whose real `admin_auth_url_4` cache is:

`server/runtime/adminapi/cache/la/bd/ac672a233d2f93c77c4e407fe7900f.php`

The real `adminapi` cache remained present throughout the revoke API calls and contained all five permissions:

- `recharge.recharge/lists`
- `recharge.recharge/refund`
- `recharge.recharge/refundAgain`
- `finance.refund/record`
- `finance.refund/log`

This explains the exact behavior:

- UI menus and button permissions were rebuilt from current database relationships and disappeared after the five `role_menu` rows were removed.
- `AuthMiddleware` continued to read the unchanged `runtime/adminapi` `admin_auth_url_4` cache and therefore allowed the same-token API calls.

## CodeGraph call chain

1. `server/app/adminapi/config/route.php` registers `InitMiddleware`, `LoginMiddleware`, then `AuthMiddleware` for the `adminapi` application.
2. `AuthMiddleware::handle()` builds `controller/action`, checks it against `AdminAuthCache::getAllUri()`, and accepts only when it is present in `AdminAuthCache::getAdminUri()`; otherwise it returns `权限不足，无法访问或操作`.
3. `AdminAuthCache` names the per-admin key `admin_auth_url_{adminId}` and caches the result of `AuthLogic::getAuthByAdminId()` for 3600 seconds.
4. `AuthLogic::getAuthByAdminId()` resolves `admin -> role -> role_menu -> system_menu.perms`.
5. `MenuApplicationService::getMenuByAdminId()` and `AuthLogic::getBtnAuthByRoleId()` query `role_menu` directly for the UI/self payload rather than reading `AdminAuthCache`, so UI and cached API authorization can diverge if the API cache is not invalidated.
6. The normal LikeAdmin role edit flow calls `(new AdminAuthCache())->deleteTag()` in `RoleApplicationService::edit()`, clearing the actual application cache tag.

## Runtime-path proof

- `think-multi-app/src/MultiApp.php` appends the selected application name to the runtime path: `runtime/{appName}/`.
- ThinkPHP's file cache driver then appends `cache/{prefix}/{md5-shard}.php`.
- Runtime config was read-only bootstrapped inside `likeadmin-php` and reported `cache.default=file`.
- `md5('admin_auth_url_4')` is `bdac672a233d2f93c77c4e407fe7900f`.
- The real `runtime/adminapi/.../bd/ac672...php` file was present, size 213 bytes, and contained all five permissions.
- The previously moved root-runtime file was also 213 bytes and contained the same five permissions, but it belongs to the wrong application runtime.

## Token identity proof

The real `adminapi` token cache was found under `server/runtime/adminapi/cache/la/...` and decoded read-only without exposing the token:

- `admin_id=4`
- `root=0` (integer)
- `account=f02op26080101`
- `role_id=[3]`
- token length `32`
- token SHA-256 `b3655f62e10543a4b4679ac57bb5abf3590abf8377bd6175373fb9d0e6b3e731`

This excludes a root-admin bypass or wrong-token explanation.

## Correct re-acceptance requirement

Repeat the revoke/restore acceptance only after invalidating the real `adminapi` authorization cache, preferably through LikeAdmin's normal `RoleApplicationService::edit()` path or by targeting the exact `runtime/adminapi/cache` object in a reversible fixture-only procedure. Do not treat the earlier API-allow result as the LikeAdmin contract.

