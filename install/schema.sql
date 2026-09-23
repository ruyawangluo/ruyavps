-- ============================================================================
--  控制面板数据库（面板端自己的库）
--
--  架构：节点自治（各自持有 Incus + 本地库 + 本地 API），面板只保存
--  「面板自己的数据」（管理员、用户、节点接入信息 ip/端口/key、实例索引、日志、设置）。
--  实例的真实状态与数据在节点端；面板通过节点 API 远程控制并缓存状态。
--  MySQL 5.7+ / MariaDB 10.2+ / utf8mb4
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nickname`      VARCHAR(64)  NOT NULL DEFAULT '',
  `role`          VARCHAR(32)  NOT NULL DEFAULT 'admin',
  `status`        TINYINT      NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     NULL,
  `last_login_ip` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户（按邮箱识别；每用户一组 API 凭据）
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(128) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL DEFAULT '',
  `nickname`      VARCHAR(64)  NOT NULL DEFAULT '',
  `status`        TINYINT      NOT NULL DEFAULT 1,
  `api_key`       VARCHAR(64)  NOT NULL DEFAULT '',
  `api_secret`    VARCHAR(64)  NOT NULL DEFAULT '',
  `remark`        VARCHAR(255) NOT NULL DEFAULT '',
  `last_login_at` DATETIME     NULL,
  `last_login_ip` VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_api_key` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 计算节点：面板只需 ip / 端口 / key 即可接入
DROP TABLE IF EXISTS `nodes`;
CREATE TABLE `nodes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(64)  NOT NULL,
  `ip`            VARCHAR(128) NOT NULL,
  `port`          INT          NOT NULL DEFAULT 8787,
  `node_key`      VARCHAR(128) NOT NULL COMMENT '节点访问密钥 X-Node-Token',
  `region`        VARCHAR(64)  NOT NULL DEFAULT '默认区域',
  `enabled`       TINYINT      NOT NULL DEFAULT 1,
  `status`        TINYINT      NOT NULL DEFAULT 0 COMMENT '1=在线 0=离线',
  `incus_version` VARCHAR(32)  NOT NULL DEFAULT '',
  `agent_version` VARCHAR(32)  NOT NULL DEFAULT '',
  `last_seen`     DATETIME     NULL,
  `remark`        VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`    DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 实例索引：面板侧记录「哪台机器在哪个节点 + 归属 + 访问 Key」，真实数据在节点端
DROP TABLE IF EXISTS `instances`;
CREATE TABLE `instances` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `node_id`      INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=管理员所有',
  `incus_name`   VARCHAR(64)  NOT NULL,
  `access_key`   VARCHAR(64)  NOT NULL COMMENT '每台机器专属访问 Key',
  `name`         VARCHAR(128) NOT NULL DEFAULT '',
  `variant`      VARCHAR(16)  NOT NULL DEFAULT 'container',
  `cpu`          INT NOT NULL DEFAULT 1,
  `memory_mb`    INT NOT NULL DEFAULT 512,
  `disk_gb`      INT NOT NULL DEFAULT 15,
  `ip`           VARCHAR(64)  NOT NULL DEFAULT '',
  `status`       VARCHAR(24)  NOT NULL DEFAULT 'creating',
  `cpu_usage`    DECIMAL(6,2) NOT NULL DEFAULT 0,
  `mem_usage_mb` INT NOT NULL DEFAULT 0,
  `remark`       VARCHAR(255) NOT NULL DEFAULT '',
  `created_at`   DATETIME NOT NULL,
  `updated_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_incus_name` (`incus_name`),
  UNIQUE KEY `uk_access_key` (`access_key`),
  KEY `idx_node` (`node_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type`  VARCHAR(16) NOT NULL DEFAULT 'admin' COMMENT 'admin/user/api/system',
  `actor_id`    INT UNSIGNED NOT NULL DEFAULT 0,
  `action`      VARCHAR(64) NOT NULL,
  `target`      VARCHAR(128) NOT NULL DEFAULT '',
  `detail`      TEXT NULL,
  `ip`          VARCHAR(45) NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_type`, `actor_id`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `k` VARCHAR(64) NOT NULL,
  `v` TEXT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `settings` (`k`, `v`) VALUES
  ('site_name', 'Incus 云控制台'),
  ('site_url',  'http://localhost:8080'),
  ('heartbeat_timeout', '90'),
  ('openapi_key', ''),
  ('openapi_secret', '');
