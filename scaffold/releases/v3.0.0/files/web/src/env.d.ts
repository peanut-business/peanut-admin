/// <reference types="vite/client" />
/// <reference types="vue/jsx" />

declare module '*.vue' {
  import { DefineComponent } from 'vue';
  // eslint-disable-next-line @typescript-eslint/no-explicit-any, @typescript-eslint/ban-types
  const component: DefineComponent<{}, {}, any>;
  export default component;
}
interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string;
  readonly VITE_DEPLOYMENT_MODE?: 'standalone' | 'multi-tenant';
}

declare module 'virtual:peanut-plugin-contributions' {
  import type { PluginFrontendContribution } from '@peanut-admin/admin/core';
  const contributions: PluginFrontendContribution[];
  export default contributions;
}
