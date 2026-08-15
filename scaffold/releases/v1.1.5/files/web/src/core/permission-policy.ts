export type PermissionEvaluator = (
  permissions: ReadonlySet<string>,
  permission: string
) => boolean;

export function evaluateRequiredPermissions(
  requiredPermissions: string | string[],
  grantedPermissions: readonly string[],
  evaluator: PermissionEvaluator
): boolean {
  const required = (
    Array.isArray(requiredPermissions)
      ? requiredPermissions
      : [requiredPermissions]
  ).filter(Boolean);
  if (required.length === 0) return true;

  const permissionSet = new Set(grantedPermissions);
  return (
    permissionSet.has('*') ||
    required.some(
      (permission) => permission !== '*' && evaluator(permissionSet, permission)
    )
  );
}
