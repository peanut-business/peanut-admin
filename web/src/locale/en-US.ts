import localeLogin from '@/views/login/locale/en-US';

import localeWorkplace from '@/views/dashboard/workplace/locale/en-US';
import localeUserSetting from '@/views/user/setting/locale/en-US';

import localeSystemMenu from '@/views/system/menu/locale/en-US';
import localeSystemRole from '@/views/system/role/locale/en-US';
import localeSystemAdmin from '@/views/system/admin/locale/en-US';
import localeSystemDept from '@/views/system/dept/locale/en-US';
import localeSystemJobs from '@/views/system/jobs/locale/en-US';
import localeSystemDict from '@/views/system/dict/locale/en-US';
import localeSystemFile from '@/views/system/file/locale/en-US';
import localeSystemCrontab from '@/views/system/crontab/locale/en-US';
import localeSystemMaintenance from '@/views/system/maintenance/locale/en-US';
import localeSystemLog from '@/views/system/log/locale/en-US';
import localeSystemConfig from '@/views/system/config/locale/en-US';
import localeMemberList from '@/views/member/list/locale/en-US';
import localeMemberTag from '@/views/member/tag/locale/en-US';
import localeNoticeChannel from '@/views/notice/channel/locale/en-US';
import localeNoticeTemplate from '@/views/notice/template/locale/en-US';
import localeNoticeLog from '@/views/notice/log/locale/en-US';
import localeFinanceAccountLog from '@/views/finance/account-log/locale/en-US';
import localeFinanceRecharge from '@/views/finance/recharge/locale/en-US';
import localeFinanceRefund from '@/views/finance/refund/locale/en-US';
import localeAppHotSearch from '@/views/app-setting/hot-search/locale/en-US';
import localeAppPay from '@/views/app-setting/pay/locale/en-US';
import localeAppChannel from '@/views/app-setting/channel/locale/en-US';
import localeAppTransaction from '@/views/app-setting/transaction/locale/en-US';
import localeArticleCate from '@/modules/official-article/views/cate/locale/en-US';
import localeArticleList from '@/modules/official-article/views/list/locale/en-US';
import localeSettings from './en-US/settings';

export default {
  'menu.dashboard': 'Dashboard',
  'menu.user': 'User Center',
  'menu.appSetting.website': 'Website Settings',
  'menu.appSetting.user': 'User Settings',
  'menu.decoration': 'Decoration',
  'menu.decoration.mobile': 'Mobile Decoration',
  'menu.decoration.tabbar': 'Tabbar Decoration',
  'menu.decoration.pc': 'PC Decoration',
  'menu.devTools': 'Developer Tools',
  'menu.devTools.code': 'Code Generator',
  'menu.devTools.modules': 'Module Governance',
  'navbar.action.locale': 'Switch to English',
  'navbar.userMenu': 'Open user menu',
  'navbar.userSettings': 'User Settings',
  'navbar.logout': 'Logout',
  'navbar.tenantSwitch': 'Switch Tenant',
  'navbar.tenantSelect': 'Select a Tenant',
  'navbar.cancel': 'Cancel',
  'navbar.confirm': 'Confirm',
  ...localeSettings,
  ...localeLogin,
  ...localeWorkplace,
  ...localeUserSetting,
  ...localeSystemMenu,
  ...localeSystemRole,
  ...localeSystemAdmin,
  ...localeSystemDept,
  ...localeSystemJobs,
  ...localeSystemDict,
  ...localeSystemFile,
  ...localeSystemCrontab,
  ...localeSystemMaintenance,
  ...localeSystemLog,
  ...localeSystemConfig,
  ...localeMemberList,
  ...localeMemberTag,
  ...localeNoticeChannel,
  ...localeNoticeTemplate,
  ...localeNoticeLog,
  ...localeFinanceAccountLog,
  ...localeFinanceRecharge,
  ...localeFinanceRefund,
  ...localeAppHotSearch,
  ...localeAppPay,
  ...localeAppChannel,
  ...localeAppTransaction,
  ...localeArticleCate,
  ...localeArticleList,
};
