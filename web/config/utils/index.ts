/**
 * Whether to generate package preview
 * 是否生成打包报告
 */
export default {};

import { resolve } from 'node:path';
import { readClientEnvironment } from '../../../scripts/client-environment';

export function isReportMode(): boolean {
  return readClientEnvironment(resolve(__dirname, '../../.env.production')).REPORT === 'true';
}
