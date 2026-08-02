SET NAMES utf8mb4;

-- 一次性把旧网站字段迁入唯一配置模型；运行时不再双读双写旧键。
SET @old_logo = (SELECT value FROM pa_config WHERE type='website' AND name='logo' LIMIT 1);
SET @old_favicon = (SELECT value FROM pa_config WHERE type='website' AND name='favicon' LIMIT 1);
SET @old_copyright = (SELECT value FROM pa_config WHERE type='website' AND name='copyright' LIMIT 1);
SET @old_icp = (SELECT value FROM pa_config WHERE type='website' AND name='icp' LIMIT 1);

INSERT INTO pa_config (type,name,value) VALUES
('website','web_favicon',COALESCE(@old_favicon,'')),
('website','web_logo',COALESCE(@old_logo,'')),
('website','login_image',''),
('website','shop_name',COALESCE((SELECT value FROM pa_config c WHERE c.type='website' AND c.name='name' LIMIT 1),'Peanut Admin')),
('website','shop_logo',COALESCE(@old_logo,'')),
('website','pc_logo',COALESCE(@old_logo,'')),
('website','pc_title',COALESCE((SELECT value FROM pa_config c WHERE c.type='website' AND c.name='name' LIMIT 1),'Peanut Admin')),
('website','pc_ico',COALESCE(@old_favicon,'')),
('website','pc_desc',''),
('website','pc_keywords',''),
('website','h5_favicon',COALESCE(@old_favicon,'')),
('agreement','service_title','服务协议'),
('agreement','service_content',''),
('agreement','privacy_title','隐私政策'),
('agreement','privacy_content',''),
('site_statistics','clarity_code',''),
('default_image','user_avatar','favicon.ico'),
('login','login_way','[1,2]'),
('login','coerce_mobile','0'),
('login','login_agreement','0'),
('pay','wx_pay_status','0'),
('pay','wx_pay_appid',''),
('pay','wx_pay_mch_id',''),
('pay','wx_pay_secret',''),
('pay','wx_pay_cert_path',''),
('pay','wx_pay_cert_key_path',''),
('pay','ali_pay_status','0'),
('pay','ali_pay_app_id',''),
('pay','ali_pay_private_key',''),
('pay','ali_pay_public_key',''),
('storage','default','local')
ON DUPLICATE KEY UPDATE value=value;

SET @copyright_json = JSON_ARRAY();
SET @copyright_json = IF(
  COALESCE(@old_copyright,'')='',
  @copyright_json,
  JSON_ARRAY_APPEND(@copyright_json,'$',JSON_OBJECT('key','版权信息','value',@old_copyright))
);
SET @copyright_json = IF(
  COALESCE(@old_icp,'')='',
  @copyright_json,
  JSON_ARRAY_APPEND(@copyright_json,'$',JSON_OBJECT('key','ICP备案','value',@old_icp))
);
INSERT INTO pa_config (type,name,value)
VALUES ('copyright','config',CAST(@copyright_json AS CHAR))
ON DUPLICATE KEY UPDATE value=value;

DELETE FROM pa_config
WHERE type='website' AND name IN ('logo','favicon','copyright','icp');
DELETE FROM pa_config WHERE type='siteStatistics';

SET @setting_root_id = (
  SELECT id FROM pa_system_menu WHERE type='M' AND paths='/app-setting' ORDER BY id LIMIT 1
);

DELETE FROM pa_system_menu WHERE LOWER(perms)='setting/pay/config/set';

INSERT INTO pa_system_menu
  (pid,type,name,icon,sort,perms,paths,component,is_cache,is_show,is_disable)
SELECT @setting_root_id,'C',seed.name,seed.icon,seed.sort,'',seed.paths,seed.component,0,1,0
FROM (
  SELECT '网站设置' name,'icon-desktop' icon,90 sort,'/system/config' paths,'system/config/index' component
  UNION ALL SELECT '用户设置','icon-user',80,'/app-setting/user','app-setting/user/index'
  UNION ALL SELECT '支付设置','icon-payment',70,'/app-setting/pay','app-setting/pay/index'
  UNION ALL SELECT '存储设置','icon-storage',60,'/system/storage','system/storage/index'
) seed
WHERE @setting_root_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM pa_system_menu m WHERE m.paths=seed.paths);

INSERT INTO pa_system_menu
  (pid,type,name,icon,sort,perms,paths,component,is_cache,is_show,is_disable)
SELECT parent.id,'A',seed.name,'',0,seed.perms,'','',0,1,0
FROM (
  SELECT '/app-setting/website' parent_path,'网站配置查看' name,'config/website' perms
  UNION ALL SELECT '/app-setting/website','网站配置保存','config/website/save'
  UNION ALL SELECT '/app-setting/website','备案配置查看','config/copyright'
  UNION ALL SELECT '/app-setting/website','备案配置保存','config/copyright/save'
  UNION ALL SELECT '/app-setting/website','协议配置查看','config/agreement'
  UNION ALL SELECT '/app-setting/website','协议配置保存','config/agreement/save'
  UNION ALL SELECT '/app-setting/website','统计配置查看','config/statistics'
  UNION ALL SELECT '/app-setting/website','统计配置保存','config/statistics/save'
  UNION ALL SELECT '/app-setting/user','用户配置查看','config/user'
  UNION ALL SELECT '/app-setting/user','用户配置保存','config/user/save'
  UNION ALL SELECT '/app-setting/user','登录配置查看','config/login'
  UNION ALL SELECT '/app-setting/user','登录配置保存','config/login/save'
  UNION ALL SELECT '/app-setting/pay','支付配置查看','setting/pay/config'
  UNION ALL SELECT '/app-setting/pay','支付配置保存','setting/pay/save'
  UNION ALL SELECT '/app-setting/storage','存储引擎列表','storage/lists'
  UNION ALL SELECT '/app-setting/storage','存储配置查看','storage/detail'
  UNION ALL SELECT '/app-setting/storage','存储配置保存','storage/setup'
  UNION ALL SELECT '/app-setting/storage','默认存储切换','storage/change'
) seed
JOIN pa_system_menu parent ON parent.paths=seed.parent_path AND parent.type='C'
WHERE NOT EXISTS (
  SELECT 1 FROM pa_system_menu m WHERE LOWER(m.perms)=LOWER(seed.perms)
);
