import type { RouteRecordNormalized } from 'vue-router';
import { deploymentMode, routesForDeployment } from '@/core/deployment-mode';

const modules = import.meta.glob('./modules/*.ts', { eager: true });
const externalModules = import.meta.glob('./externalModules/*.ts', {
  eager: true,
});

function formatModules(_modules: any, result: RouteRecordNormalized[]) {
  Object.keys(_modules).forEach((key) => {
    const defaultModule = _modules[key].default;
    if (!defaultModule) return;
    const moduleList = Array.isArray(defaultModule)
      ? [...defaultModule]
      : [defaultModule];
    result.push(...moduleList);
  });
  return result;
}

const mode = deploymentMode(import.meta.env.VITE_DEPLOYMENT_MODE);

export const appRoutes: RouteRecordNormalized[] = routesForDeployment(
  formatModules(modules, []),
  mode
) as RouteRecordNormalized[];

export const appExternalRoutes: RouteRecordNormalized[] = routesForDeployment(
  formatModules(externalModules, []),
  mode
) as RouteRecordNormalized[];
