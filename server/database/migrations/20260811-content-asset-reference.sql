SET NAMES utf8mb4;

-- PB06 新资源引用会保留云/CDN 绝对 URL，封面字段必须容纳完整地址。
ALTER TABLE `pa_article`
  MODIFY COLUMN `image` VARCHAR(2048) NULL DEFAULT NULL COMMENT '文章图片：local 相对 URI 或云/CDN 绝对 URL';

ALTER TABLE `pa_decorate_tabbar`
  MODIFY COLUMN `selected` VARCHAR(2048) NOT NULL DEFAULT '' COMMENT '选中图标：local 相对 URI 或云/CDN 绝对 URL',
  MODIFY COLUMN `unselected` VARCHAR(2048) NOT NULL DEFAULT '' COMMENT '未选图标：local 相对 URI 或云/CDN 绝对 URL';
