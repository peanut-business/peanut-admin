import { evaluateRequiredPermissions } from '../../src/core/permission-policy';

const exactEvaluator = (
  permissions: ReadonlySet<string>,
  permission: string
): boolean => permissions.has(permission);

function expect(condition: boolean, message: string): void {
  if (!condition) throw new Error(message);
}

expect(evaluateRequiredPermissions('', [], exactEvaluator), 'empty string must pass');
expect(evaluateRequiredPermissions([], [], exactEvaluator), 'empty list must pass');
expect(
  evaluateRequiredPermissions('article/lists', ['*'], exactEvaluator),
  'root wildcard grant must pass'
);
expect(
  evaluateRequiredPermissions('article/lists', ['article/lists'], exactEvaluator),
  'exact single permission must pass'
);
expect(
  evaluateRequiredPermissions(
    ['article/edit', 'article/lists'],
    ['article/lists'],
    exactEvaluator
  ),
  'multiple requirements must use any-of'
);
expect(
  !evaluateRequiredPermissions('article/edit', ['article/lists'], exactEvaluator),
  'missing permission must fail'
);
expect(
  !evaluateRequiredPermissions('*', ['article/lists'], exactEvaluator),
  'requesting wildcard must not bypass authorization'
);

console.log('PB04-AUTH-HOST-001 Web passed');
