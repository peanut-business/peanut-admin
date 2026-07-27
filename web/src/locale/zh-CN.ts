import localeLogin from '@/views/login/locale/zh-CN';

import localeWorkplace from '@/views/dashboard/workplace/locale/zh-CN';
import localeUserSetting from '@/views/user/setting/locale/zh-CN';

import localeSystemMenu from '@/views/system/menu/locale/zh-CN';
import localeSystemRole from '@/views/system/role/locale/zh-CN';
import localeSystemAdmin from '@/views/system/admin/locale/zh-CN';
import localeSystemDept from '@/views/system/dept/locale/zh-CN';
import localeSystemJobs from '@/views/system/jobs/locale/zh-CN';
import localeSystemDict from '@/views/system/dict/locale/zh-CN';
import localeSystemFile from '@/views/system/file/locale/zh-CN';
import localeSystemCrontab from '@/views/system/crontab/locale/zh-CN';
import localeSystemMaintenance from '@/views/system/maintenance/locale/zh-CN';
import localeSystemLog from '@/views/system/log/locale/zh-CN';
import localeSystemConfig from '@/views/system/config/locale/zh-CN';
import localeSystemStorage from '@/views/system/storage/locale/zh-CN';
import localeMemberList from '@/views/member/list/locale/zh-CN';
import localeMemberTag from '@/views/member/tag/locale/zh-CN';
import localeNoticeChannel from '@/views/notice/channel/locale/zh-CN';
import localeNoticeTemplate from '@/views/notice/template/locale/zh-CN';
import localeNoticeLog from '@/views/notice/log/locale/zh-CN';
import localeFinanceAccountLog from '@/views/finance/account-log/locale/zh-CN';
import localeAppHotSearch from '@/views/app-setting/hot-search/locale/zh-CN';
import localeAppCustomerService from '@/views/app-setting/customer-service/locale/zh-CN';
import localeSettings from './zh-CN/settings';

export default {
  'menu.dashboard': '仪表盘',
  'menu.user': '个人中心',
  'navbar.action.locale': '切换为中文',
  'navbar.userSettings': '用户设置',
  'navbar.logout': '退出登录',
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
  ...localeSystemStorage,
  ...localeMemberList,
  ...localeMemberTag,
  ...localeNoticeChannel,
  ...localeNoticeTemplate,
  ...localeNoticeLog,
  ...localeFinanceAccountLog,
  ...localeAppHotSearch,
  ...localeAppCustomerService,
};
