import type { RouteRecordRaw } from 'vue-router';

export type DeploymentMode = 'standalone' | 'multi-tenant';

export function deploymentMode(value: unknown): DeploymentMode | undefined {
  return value === 'standalone' || value === 'multi-tenant'
    ? value
    : undefined;
}

export function routesForDeployment(
  routes: RouteRecordRaw[],
  mode: DeploymentMode | undefined
): RouteRecordRaw[] {
  return routes.reduce<RouteRecordRaw[]>((visible, route) => {
    if (mode !== 'multi-tenant' && route.meta?.controlPlane !== undefined) {
      return visible;
    }
    if (mode !== 'standalone' && route.meta?.instanceTool === true) {
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
