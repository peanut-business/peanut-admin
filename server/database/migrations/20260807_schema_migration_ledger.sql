CREATE TABLE IF NOT EXISTS `pa_schema_migration` (
  `migration` varchar(191) NOT NULL COMMENT '迁移文件名',
  `checksum` char(64) NOT NULL COMMENT '迁移文件 SHA-256',
  `batch` int unsigned NOT NULL DEFAULT 0 COMMENT '执行批次',
  `status` varchar(16) NOT NULL DEFAULT 'running' COMMENT 'running/applied/failed',
  `started_at` bigint unsigned NOT NULL DEFAULT 0 COMMENT '开始时间',
  `applied_at` bigint unsigned DEFAULT NULL COMMENT '完成时间',
  `error` varchar(1000) NOT NULL DEFAULT '' COMMENT '失败摘要',
  PRIMARY KEY (`migration`),
  KEY `idx_status_batch` (`status`, `batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移账本';
