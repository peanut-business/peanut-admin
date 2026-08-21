import type { RouteRecordNormalized } from 'vue-router';
import {
  allowsInstanceTools,
  deploymentMode,
  routesForDeployment,
} from '@peanut-admin/admin/shell';
import { pluginRoutes } from './plugin-contributions';

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

const configuredMode = import.meta.env.VITE_DEPLOYMENT_MODE;
const mode = deploymentMode(configuredMode);
const instanceToolsAllowed = allowsInstanceTools(configuredMode);

export const appRoutes: RouteRecordNormalized[] = routesForDeployment(
  [...formatModules(modules, []), ...pluginRoutes],
  mode,
  instanceToolsAllowed
) as RouteRecordNormalized[];

export const appExternalRoutes: RouteRecordNormalized[] = routesForDeployment(
  formatModules(externalModules, []),
  mode,
  instanceToolsAllowed
) as RouteRecordNormalized[];
