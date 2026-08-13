import type { RouteRecordRaw } from 'vue-router';

export type DeploymentMode = 'standalone' | 'multi-tenant';

export function deploymentMode(value: unknown): DeploymentMode {
  return value === 'multi-tenant' ? 'multi-tenant' : 'standalone';
}

export function allowsInstanceTools(value: unknown): boolean {
  return value === 'standalone';
}

export function routesForDeployment(
  routes: RouteRecordRaw[],
  mode: DeploymentMode,
  instanceToolsAllowed = mode === 'standalone'
): RouteRecordRaw[] {
  return routes.reduce<RouteRecordRaw[]>((visible, route) => {
    if (mode !== 'multi-tenant' && route.meta?.controlPlane !== undefined) {
      return visible;
    }
    if (!instanceToolsAllowed && route.meta?.instanceTool === true) {
      return visible;
    }
    visible.push({
      ...route,
      children: route.children
        ? routesForDeployment(route.children, mode, instanceToolsAllowed)
        : route.children,
    } as RouteRecordRaw);
    return visible;
  }, []);
}
