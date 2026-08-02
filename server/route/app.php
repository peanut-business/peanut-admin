<?php
declare(strict_types=1);

use app\adminapi\controller\auth\AdminController;
use app\adminapi\controller\auth\LoginController;
use app\adminapi\controller\auth\MenuController;
use app\adminapi\controller\auth\RoleController;
use app\adminapi\controller\WorkbenchController;
use app\api\controller\IndexController as ApiIndexController;
use app\api\controller\LoginController as ApiLoginController;
use app\api\controller\SmsController as ApiSmsController;
use app\api\controller\UserController as ApiUserController;
use app\api\controller\ArticleController as ApiArticleController;
use app\api\controller\SearchController as ApiSearchController;
use app\api\controller\UploadController as ApiUploadController;
use app\api\controller\AccountLogController as ApiAccountLogController;
use app\api\controller\PcController as ApiPcController;
use app\api\controller\OfficialAccountController as ApiOfficialAccountController;
use app\api\controller\DecorationController as ApiDecorationController;
use app\api\controller\RechargeController as ApiRechargeController;
use app\api\controller\PaymentNotifyController as ApiPaymentNotifyController;
use app\api\controller\OAuthController as ApiOAuthController;
use app\api\middleware\CheckTokenMiddleware;
use app\adminapi\controller\config\ConfigController;
use app\adminapi\controller\member\MemberController;
use app\adminapi\controller\member\MemberTagController;
use app\adminapi\controller\notice\NoticeChannelController;
use app\adminapi\controller\notice\NoticeTemplateController;
use app\adminapi\controller\notice\NoticeLogController;
use app\adminapi\controller\notice\NoticeSceneController;
use app\adminapi\controller\dept\DeptController;
use app\adminapi\controller\dept\JobsController;
use app\adminapi\controller\dict\DictTypeController;
use app\adminapi\controller\dict\DictDataController;
use app\adminapi\controller\file\FileController;
use app\adminapi\controller\file\UploadController;
use app\adminapi\controller\crontab\CrontabController;
use app\adminapi\controller\generator\GeneratorController;
use app\adminapi\controller\system\SystemController;
use app\adminapi\controller\setting\StorageController;
use app\adminapi\controller\setting\HotSearchController;
use app\adminapi\controller\setting\CustomerServiceController;
use app\adminapi\controller\setting\PayConfigController;
use app\adminapi\controller\setting\RechargeSettingController;
use app\adminapi\controller\setting\ChannelController;
use app\adminapi\controller\setting\TransactionSettingsController;
use app\adminapi\controller\setting\WebPageController;
use app\adminapi\controller\setting\MiniProgramController;
use app\adminapi\controller\setting\OfficialAccountController;
use app\adminapi\controller\setting\OfficialAccountMenuController;
use app\adminapi\controller\setting\OfficialAccountReplyController;
use app\adminapi\controller\setting\OpenPlatformController;
use app\adminapi\controller\decoration\DecorationPageController;
use app\adminapi\controller\decoration\DecorationTabbarController;
use app\adminapi\controller\log\OperationLogController;
use app\adminapi\controller\finance\AccountLogController;
use app\adminapi\controller\finance\RechargeController;
use app\adminapi\controller\finance\RefundController;
use app\adminapi\controller\article\ArticleController;
use app\adminapi\controller\article\ArticleCateController;
use app\adminapi\http\middleware\AuthMiddleware;
use app\adminapi\http\middleware\LoginMiddleware;
use app\adminapi\http\middleware\OperationLogMiddleware;
use think\facade\Route;

// ─── 免登录路由（不挂任何鉴权中间件） ──────────────────────────────────────
Route::post('api/user/login',  [LoginController::class, 'login']);
Route::post('api/user/logout', [LoginController::class, 'logout']);
Route::post('admin/login/login',  [LoginController::class, 'login']);
Route::post('admin/login/logout', [LoginController::class, 'logout']);

// ─── Arco Design Pro Vue 兼容路由（仅需登录，不做 RBAC） ─────────────────────
Route::group(function () {
    Route::post('api/user/info', [LoginController::class, 'info']);
    Route::post('api/user/menu', [MenuController::class, 'route']);
})->middleware(LoginMiddleware::class);

// ─── 管理后台完整 API（Login → Auth 两层中间件） ─────────────────────────────
// 前缀统一挂在 api/ 下，前端 vite 代理只转发 /api，生产 nginx 也只需转一条前缀。
Route::group('api/admin', function () {
    Route::get('login/info', [LoginController::class, 'info']);

    // 工作台
    Route::get('workbench/index', [WorkbenchController::class, 'index']);

    // 菜单
    Route::get('menu/route',   [MenuController::class, 'route']);
    Route::get('menu/lists',   [MenuController::class, 'lists']);
    Route::get('menu/all',     [MenuController::class, 'all']);
    Route::get('menu/detail',  [MenuController::class, 'detail']);
    Route::post('menu/add',    [MenuController::class, 'add']);
    Route::post('menu/edit',   [MenuController::class, 'edit']);
    Route::post('menu/delete', [MenuController::class, 'delete']);
    Route::post('menu/status', [MenuController::class, 'updateStatus']);

    // 角色
    Route::get('role/lists',   [RoleController::class, 'lists']);
    Route::get('role/all',     [RoleController::class, 'all']);
    Route::get('role/detail',  [RoleController::class, 'detail']);
    Route::post('role/add',    [RoleController::class, 'add']);
    Route::post('role/edit',   [RoleController::class, 'edit']);
    Route::post('role/delete', [RoleController::class, 'delete']);

    // 管理员
    Route::get('admin/lists',   [AdminController::class, 'lists']);
    Route::get('admin/detail',  [AdminController::class, 'detail']);
    Route::get('admin/self',    [AdminController::class, 'self']);
    Route::post('admin/editSelf', [AdminController::class, 'editSelf']);
    Route::post('admin/add',    [AdminController::class, 'add']);
    Route::post('admin/edit',   [AdminController::class, 'edit']);
    Route::post('admin/delete', [AdminController::class, 'delete']);
    Route::post('admin/status', [AdminController::class, 'updateStatus']);

    // 部门
    Route::get('dept/lists',   [DeptController::class, 'lists']);
    Route::get('dept/all',     [DeptController::class, 'all']);
    Route::get('dept/leaderDept', [DeptController::class, 'leaderDept']);
    Route::get('dept/detail',  [DeptController::class, 'detail']);
    Route::post('dept/add',    [DeptController::class, 'add']);
    Route::post('dept/edit',   [DeptController::class, 'edit']);
    Route::post('dept/delete', [DeptController::class, 'delete']);
    Route::post('dept/status', [DeptController::class, 'updateStatus']);

    // 岗位
    Route::get('jobs/lists',   [JobsController::class, 'lists']);
    Route::get('jobs/all',     [JobsController::class, 'all']);
    Route::get('jobs/detail',  [JobsController::class, 'detail']);
    Route::post('jobs/add',    [JobsController::class, 'add']);
    Route::post('jobs/edit',   [JobsController::class, 'edit']);
    Route::post('jobs/delete', [JobsController::class, 'delete']);
    Route::post('jobs/status', [JobsController::class, 'updateStatus']);

    // 字典类型
    Route::get('dict/type/lists',   [DictTypeController::class, 'lists']);
    Route::get('dict/type/all',     [DictTypeController::class, 'all']);
    Route::get('dict/type/detail',  [DictTypeController::class, 'detail']);
    Route::post('dict/type/add',    [DictTypeController::class, 'add']);
    Route::post('dict/type/edit',   [DictTypeController::class, 'edit']);
    Route::post('dict/type/delete', [DictTypeController::class, 'delete']);
    Route::post('dict/type/status', [DictTypeController::class, 'updateStatus']);

    // 字典数据
    Route::get('dict/data/lists',   [DictDataController::class, 'lists']);
    Route::get('dict/data/byType',  [DictDataController::class, 'byType']);
    Route::get('dict/data/detail',  [DictDataController::class, 'detail']);
    Route::post('dict/data/add',    [DictDataController::class, 'add']);
    Route::post('dict/data/edit',   [DictDataController::class, 'edit']);
    Route::post('dict/data/delete', [DictDataController::class, 'delete']);
    Route::post('dict/data/status', [DictDataController::class, 'updateStatus']);

    // 素材库 - 上传
    Route::post('upload/image', [UploadController::class, 'image']);
    Route::post('upload/video', [UploadController::class, 'video']);
    Route::post('upload/file',  [UploadController::class, 'file']);

    // 素材库 - 文件
    Route::get('file/lists',   [FileController::class, 'lists']);
    Route::post('file/move',   [FileController::class, 'move']);
    Route::post('file/rename', [FileController::class, 'rename']);
    Route::post('file/delete', [FileController::class, 'delete']);

    // 素材库 - 分类
    Route::get('file/cate/lists',   [FileController::class, 'listCate']);
    Route::post('file/cate/add',    [FileController::class, 'addCate']);
    Route::post('file/cate/edit',   [FileController::class, 'editCate']);
    Route::post('file/cate/delete', [FileController::class, 'delCate']);

    // 定时任务
    Route::get('crontab/lists',      [CrontabController::class, 'lists']);
    Route::get('crontab/detail',     [CrontabController::class, 'detail']);
    Route::get('crontab/expression', [CrontabController::class, 'expression']);
    Route::post('crontab/add',       [CrontabController::class, 'add']);
    Route::post('crontab/edit',      [CrontabController::class, 'edit']);
    Route::post('crontab/delete',    [CrontabController::class, 'delete']);
    Route::post('crontab/operate',   [CrontabController::class, 'operate']);

    // 开发工具 - 安全代码生成器
    Route::get('generator/source-tables', [GeneratorController::class, 'sourceTables']);
    Route::get('generator/lists', [GeneratorController::class, 'lists']);
    Route::get('generator/detail', [GeneratorController::class, 'detail']);
    Route::post('generator/import', [GeneratorController::class, 'import']);
    Route::post('generator/sync', [GeneratorController::class, 'sync']);
    Route::post('generator/update', [GeneratorController::class, 'update']);
    Route::post('generator/delete', [GeneratorController::class, 'delete']);
    Route::post('generator/preview', [GeneratorController::class, 'preview']);
    Route::post('generator/generate', [GeneratorController::class, 'generate']);
    Route::get('generator/download', [GeneratorController::class, 'download']);
    Route::get('generator/models', [GeneratorController::class, 'models']);

    // 系统维护
    Route::get('system/info',        [SystemController::class, 'info']);
    Route::post('system/clearCache', [SystemController::class, 'clearCache']);

    // 操作日志
    Route::get('log/lists',  [OperationLogController::class, 'lists']);
    Route::post('log/clear', [OperationLogController::class, 'clear']);

    // 系统配置 - 网站设置
    Route::get('config/website',      [ConfigController::class, 'getWebsite']);
    Route::post('config/website/save', [ConfigController::class, 'saveWebsite']);
    Route::get('config/copyright', [ConfigController::class, 'getCopyright']);
    Route::post('config/copyright/save', [ConfigController::class, 'saveCopyright']);
    Route::get('config/agreement', [ConfigController::class, 'getAgreement']);
    Route::post('config/agreement/save', [ConfigController::class, 'saveAgreement']);
    Route::get('config/statistics', [ConfigController::class, 'getStatistics']);
    Route::post('config/statistics/save', [ConfigController::class, 'saveStatistics']);
    Route::get('config/user', [ConfigController::class, 'getUser']);
    Route::post('config/user/save', [ConfigController::class, 'saveUser']);
    Route::get('config/login', [ConfigController::class, 'getLogin']);
    Route::post('config/login/save', [ConfigController::class, 'saveLogin']);

    // 系统配置 - 存储设置
    Route::get('storage/lists',   [StorageController::class, 'lists']);
    Route::get('storage/detail',  [StorageController::class, 'detail']);
    Route::post('storage/setup',  [StorageController::class, 'setup']);
    Route::post('storage/change', [StorageController::class, 'change']);

    // 会员列表
    Route::get('member/lists',          [MemberController::class, 'lists']);
    Route::get('member/detail',         [MemberController::class, 'detail']);
    Route::post('member/add',           [MemberController::class, 'add']);
    Route::post('member/edit',          [MemberController::class, 'edit']);
    Route::post('member/profile/edit',  [MemberController::class, 'profileEdit']);
    Route::post('member/status',        [MemberController::class, 'updateStatus']);
    Route::post('member/adjustBalance', [MemberController::class, 'adjustBalance']);
    Route::post('member/adjustMoney',   [MemberController::class, 'adjustMoney']);

    // LikeAdmin 1.9.4 用户详情契约路由；member 路由保留作 Peanut 兼容别名。
    Route::get('user.user/detail',       [MemberController::class, 'detail']);
    Route::post('user.user/edit',        [MemberController::class, 'edit']);
    Route::post('user.user/adjustMoney', [MemberController::class, 'adjustMoney']);

    // 会员标签
    Route::get('member/tag/lists',   [MemberTagController::class, 'lists']);
    Route::post('member/tag/add',    [MemberTagController::class, 'add']);
    Route::post('member/tag/edit',   [MemberTagController::class, 'edit']);
    Route::post('member/tag/delete', [MemberTagController::class, 'delete']);

    // 通知渠道配置
    Route::get('notice/channel/detail', [NoticeChannelController::class, 'detail']);
    Route::post('notice/channel/save',  [NoticeChannelController::class, 'save']);

    // 通知模板
    Route::get('notice/template/lists',   [NoticeTemplateController::class, 'lists']);
    Route::post('notice/template/add',    [NoticeTemplateController::class, 'add']);
    Route::post('notice/template/edit',   [NoticeTemplateController::class, 'edit']);
    Route::post('notice/template/delete', [NoticeTemplateController::class, 'delete']);

    // 通知发送日志
    Route::get('notice/log/lists',  [NoticeLogController::class, 'lists']);
    Route::get('notice/log/detail', [NoticeLogController::class, 'detail']);

    // 通知业务场景
    Route::get('notice/scene/lists',  [NoticeSceneController::class, 'lists']);
    Route::get('notice/scene/detail', [NoticeSceneController::class, 'detail']);
    Route::post('notice/scene/save',  [NoticeSceneController::class, 'save']);

    // 内容管理 - 文章分类
    Route::get('article.articleCate/lists',  [ArticleCateController::class, 'lists']);
    Route::get('article.articleCate/all',    [ArticleCateController::class, 'all']);
    Route::get('article.articleCate/detail', [ArticleCateController::class, 'detail']);
    Route::post('article.articleCate/add',          [ArticleCateController::class, 'add']);
    Route::post('article.articleCate/edit',         [ArticleCateController::class, 'edit']);
    Route::post('article.articleCate/delete',       [ArticleCateController::class, 'delete']);
    Route::post('article.articleCate/updateStatus', [ArticleCateController::class, 'updateStatus']);

    // 内容管理 - 文章
    Route::get('article.article/lists',  [ArticleController::class, 'lists']);
    Route::get('article.article/detail', [ArticleController::class, 'detail']);
    Route::post('article.article/add',          [ArticleController::class, 'add']);
    Route::post('article.article/edit',         [ArticleController::class, 'edit']);
    Route::post('article.article/delete',       [ArticleController::class, 'delete']);
    Route::post('article.article/updateStatus', [ArticleController::class, 'updateStatus']);

    // 财务 - 账户流水
    Route::get('finance/account-log/lists', [AccountLogController::class, 'lists']);
    Route::get('finance/account-log/change-types', [AccountLogController::class, 'getUmChangeType']);

    // LikeAdmin 1.9.4 余额明细契约路由；REST 路由保留作 Peanut 兼容别名。
    Route::get('finance.account_log/lists', [AccountLogController::class, 'lists']);
    Route::get('finance.account_log/getUmChangeType', [AccountLogController::class, 'getUmChangeType']);

    // 应用设置 - 热门搜索
    Route::get('setting/hot-search/config',  [HotSearchController::class, 'getConfig']);
    Route::post('setting/hot-search/save',   [HotSearchController::class, 'setConfig']);

    // 应用设置 - 客服设置
    Route::get('setting/customer-service/config', [CustomerServiceController::class, 'getConfig']);
    Route::post('setting/customer-service/save',  [CustomerServiceController::class, 'setConfig']);

    // 应用设置 - 支付配置
    Route::get('setting/pay/config',  [PayConfigController::class, 'getConfig']);
    Route::post('setting/pay/save',   [PayConfigController::class, 'setConfig']);
    Route::get('setting/recharge/config', [RechargeSettingController::class, 'config']);
    Route::post('setting/recharge/save', [RechargeSettingController::class, 'save']);

    // 应用设置 - 渠道配置
    Route::get('setting/channel/config',  [ChannelController::class, 'getConfig']);
    Route::post('setting/channel/save',   [ChannelController::class, 'setConfig']);

    // 应用设置 - 交易设置
    Route::get('setting/transaction/config',  [TransactionSettingsController::class, 'getConfig']);
    Route::post('setting/transaction/save',   [TransactionSettingsController::class, 'setConfig']);

    // 渠道设置 - H5 网页渠道
    Route::get('setting/web-page/config', [WebPageController::class, 'getConfig']);
    Route::post('setting/web-page/save',  [WebPageController::class, 'setConfig']);

    // 渠道设置 - 微信小程序
    Route::get('setting/mini-program/config', [MiniProgramController::class, 'getConfig']);
    Route::post('setting/mini-program/save', [MiniProgramController::class, 'setConfig']);

    // 渠道设置 - 微信公众号与开放平台
    Route::get('setting/official-account/config', [OfficialAccountController::class, 'getConfig']);
    Route::post('setting/official-account/save', [OfficialAccountController::class, 'setConfig']);
    Route::get('setting/official-account/menu', [OfficialAccountMenuController::class, 'detail']);
    Route::post('setting/official-account/menu/save', [OfficialAccountMenuController::class, 'save']);
    Route::post('setting/official-account/menu/publish', [OfficialAccountMenuController::class, 'saveAndPublish']);
    Route::get('setting/official-account/reply/lists', [OfficialAccountReplyController::class, 'lists']);
    Route::get('setting/official-account/reply/detail', [OfficialAccountReplyController::class, 'detail']);
    Route::post('setting/official-account/reply/add', [OfficialAccountReplyController::class, 'add']);
    Route::post('setting/official-account/reply/edit', [OfficialAccountReplyController::class, 'edit']);
    Route::post('setting/official-account/reply/delete', [OfficialAccountReplyController::class, 'delete']);
    Route::post('setting/official-account/reply/status', [OfficialAccountReplyController::class, 'updateStatus']);
    Route::get('setting/open-platform/config', [OpenPlatformController::class, 'getConfig']);
    Route::post('setting/open-platform/save', [OpenPlatformController::class, 'setConfig']);

    // 装修管理：移动端、Tabbar 与 PC 权限域严格分离
    Route::get('decoration/mobile/page/lists', [DecorationPageController::class, 'mobileLists']);
    Route::get('decoration/mobile/page/detail', [DecorationPageController::class, 'mobileDetail']);
    Route::post('decoration/mobile/page/save', [DecorationPageController::class, 'mobileSave']);
    Route::get('decoration/mobile/article', [DecorationPageController::class, 'article']);
    Route::get('decoration/tabbar/detail', [DecorationTabbarController::class, 'detail']);
    Route::post('decoration/tabbar/save', [DecorationTabbarController::class, 'save']);
    Route::get('decoration/pc/page/lists', [DecorationPageController::class, 'pcLists']);
    Route::get('decoration/pc/page/detail', [DecorationPageController::class, 'pcDetail']);
    Route::post('decoration/pc/page/save', [DecorationPageController::class, 'pcSave']);

    // 财务 - 充值订单
    Route::get('finance/recharge/lists', [RechargeController::class, 'lists']);
    Route::post('finance/recharge/refund', [RechargeController::class, 'refund']);
    Route::post('finance/recharge/refundAgain', [RechargeController::class, 'refundAgain']);

    // LikeAdmin 1.9.4 充值契约路由；上面的 REST 路由作为 Peanut 兼容别名。
    Route::get('recharge.recharge/lists', [RechargeController::class, 'lists']);
    Route::post('recharge.recharge/refund', [RechargeController::class, 'refund']);
    Route::post('recharge.recharge/refundAgain', [RechargeController::class, 'refundAgain']);

    // 财务 - 退款模块
    Route::get('finance/refund/stat',   [RefundController::class, 'stat']);
    Route::get('finance/refund/record', [RefundController::class, 'record']);
    Route::get('finance/refund/log',    [RefundController::class, 'log']);

    // LikeAdmin 1.9.4 退款契约路由；stat 按参考不登记权限字符。
    Route::get('finance.refund/stat',   [RefundController::class, 'stat']);
    Route::get('finance.refund/record', [RefundController::class, 'record']);
    Route::get('finance.refund/log',    [RefundController::class, 'log']);
})->middleware([LoginMiddleware::class, AuthMiddleware::class, OperationLogMiddleware::class]);

// ═══════════════════════════════════════════════════════════════════════════════
// 用户端 API（/api/user/ 和 /api/  命名空间）
// 公开接口无中间件；需登录接口挂 CheckTokenMiddleware
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 公开接口（无需 token） ────────────────────────────────────────────────────
Route::get('api/index/index',   [ApiIndexController::class, 'index']);
Route::get('api/index/config',  [ApiIndexController::class, 'config']);
Route::get('api/index/policy',  [ApiIndexController::class, 'policy']);

Route::post('api/login/register', [ApiLoginController::class, 'register']);
Route::post('api/login/account',  [ApiLoginController::class, 'account']);
Route::post('api/login/mobile',   [ApiLoginController::class, 'mobile']);
Route::post('api/login/resetPassword', [ApiLoginController::class, 'resetPassword']);
Route::post('api/login/logout',   [ApiLoginController::class, 'logout']);
Route::post('api/sms/sendCode',   [ApiSmsController::class, 'sendCode']);

// 微信 OAuth 匿名流程；仅 completion ticket 可进入补全接口，不是会员 token。
Route::post('api/oauth/wechat/begin', [ApiOAuthController::class, 'begin']);
Route::post('api/oauth/wechat/callback', [ApiOAuthController::class, 'callback']);
Route::post('api/oauth/wechat/mini-program', [ApiOAuthController::class, 'miniProgram']);
Route::post('api/oauth/wechat/complete', [ApiOAuthController::class, 'complete']);

// 支付渠道回调：匿名入口，但业务处理前必须完成渠道验签。
Route::post('api/payment/notify/wechat', [ApiPaymentNotifyController::class, 'wechat']);
Route::post('api/payment/notify/alipay', [ApiPaymentNotifyController::class, 'alipay']);

Route::get('api/article/cate',    [ApiArticleController::class, 'cate']);
Route::get('api/article/lists',   [ApiArticleController::class, 'lists']);
Route::get('api/article/detail',  [ApiArticleController::class, 'detail']);

Route::get('api/search/hotLists', [ApiSearchController::class, 'hotLists']);

Route::post('api/upload/image',   [ApiUploadController::class, 'image']);

// 装修消费（匿名只读，保存后立即生效）
Route::get('api/decoration/mobile', [ApiDecorationController::class, 'mobilePage']);
Route::get('api/decoration/tabbar', [ApiDecorationController::class, 'tabbar']);
Route::get('api/decoration/pc', [ApiDecorationController::class, 'pcPage']);

// 微信公众号服务器回调（微信平台调用，必须免登录）
Route::get('api/wechat/official-account/callback', [ApiOfficialAccountController::class, 'verify']);
Route::post('api/wechat/official-account/callback', [ApiOfficialAccountController::class, 'callback']);

// PC 端聚合（公开）
Route::get('api/pc/config',         [ApiPcController::class, 'config']);
Route::get('api/pc/index',          [ApiPcController::class, 'index']);
Route::get('api/pc/infoCenter',     [ApiPcController::class, 'infoCenter']);
Route::get('api/pc/articleDetail',  [ApiPcController::class, 'articleDetail']);

// ─── 需登录接口（挂 CheckTokenMiddleware） ──────────────────────────────────
Route::group('api', function () {
    // 用户信息
    Route::get('user/center',         [ApiUserController::class, 'center']);
    Route::get('user/info',           [ApiUserController::class, 'info']);
    Route::post('user/setInfo',       [ApiUserController::class, 'setInfo']);
    Route::post('user/changePassword',[ApiUserController::class, 'changePassword']);
    Route::post('user/bindMobile',    [ApiUserController::class, 'bindMobile']);

    // 文章收藏
    Route::post('article/addCollect',    [ApiArticleController::class, 'addCollect']);
    Route::post('article/cancelCollect', [ApiArticleController::class, 'cancelCollect']);
    Route::get('article/collect',        [ApiArticleController::class, 'collect']);

    // 账户流水
    Route::get('account_log/lists', [ApiAccountLogController::class, 'lists']);

    // 已登录会员绑定微信身份；会员 ID 只取当前 token。
    Route::post('oauth/wechat/bind', [ApiOAuthController::class, 'bind']);

    // 用户充值：配置、订单、预支付和本人记录均要求会员登录。
    Route::get('recharge/config', [ApiRechargeController::class, 'config']);
    Route::post('recharge/create', [ApiRechargeController::class, 'create']);
    Route::post('recharge/prepay', [ApiRechargeController::class, 'prepay']);
    Route::get('recharge/detail', [ApiRechargeController::class, 'detail']);
    Route::get('recharge/lists', [ApiRechargeController::class, 'lists']);
})->middleware([CheckTokenMiddleware::class]);
