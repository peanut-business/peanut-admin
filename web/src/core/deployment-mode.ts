import type { RouteRecordRaw } from 'vue-router';

export type DeploymentMode = 'standalone' | 'multi-tenant';

export function deploymentMode(value: unknown): DeploymentMode {
  return value === 'multi-tenant' ? 'multi-tenant' : 'standalone';
}

export function routesForDeployment(
  routes: RouteRecordRaw[],
  mode: DeploymentMode
): RouteRecordRaw[] {
  return routes.reduce<RouteRecordRaw[]>((visible, route) => {
    if (mode !== 'multi-tenant' && route.meta?.controlPlane !== undefined) {
      return visible;
    }
    visible.push({
      ...route,
      children: route.children
        ? routesForDeployment(route.children, mode)
        : route.children,
    } as RouteRecordRaw);
    return visible;
  }, []);
}
