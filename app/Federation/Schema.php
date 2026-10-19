<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Database;

/**
 * Tabelle e colonne dell'ActivityPub, create al volo sulle installazioni
 * già vive: su hosting condiviso non c'è un migratore da lanciare a mano.
 */
final class Schema
{
    public static function ensure(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $db = Database::instance();

        self::ensureFederationColumn($db);
        self::ensureTables($db);
        self::ensureNotificationParent($db);
        self::ensureNotificationDeleted($db);
    }

    private static function ensureFederationColumn(Database $db): void
    {
        if ($db->columnExists('blogs', 'federation_active')) {
            return;
        }

        try {
            $db->query(
                'ALTER TABLE {{blogs}} ADD COLUMN `federation_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `discoverable`'
            );
        } catch (\Throwable $e) {
            if (!$db->columnExists('blogs', 'federation_active')) {
                throw $e;
            }
        }
    }

    private static function ensureTables(Database $db): void
    {
        $prefix = $db->prefix();
        $statements = [
            'ap_keys' =>
            "CREATE TABLE IF NOT EXISTS `{$prefix}ap_keys` (
              `blog_id`     INT UNSIGNED NOT NULL,
              `public_key`  TEXT NOT NULL,
              `private_key` TEXT NOT NULL,
              `created_at`  DATETIME NOT NULL,
              PRIMARY KEY (`blog_id`),
              CONSTRAINT `fk_ap_keys_blog` FOREIGN KEY (`blog_id`)
                REFERENCES `{$prefix}blogs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ap_followers' => "CREATE TABLE IF NOT EXISTS `{$prefix}ap_followers` (
              `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `blog_id`          INT UNSIGNED NOT NULL,
              `actor_id`         VARCHAR(500) NOT NULL,
              `inbox_url`        VARCHAR(500) NOT NULL,
              `shared_inbox_url` VARCHAR(500) DEFAULT NULL,
              `handle`           VARCHAR(191) DEFAULT NULL,
              `display_name`     VARCHAR(200) DEFAULT NULL,
              `avatar_url`       VARCHAR(500) DEFAULT NULL,
              `created_at`       DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_ap_followers_actor` (`blog_id`,`actor_id`(191)),
              KEY `ix_ap_followers_blog` (`blog_id`,`created_at`),
              CONSTRAINT `fk_ap_followers_blog` FOREIGN KEY (`blog_id`)
                REFERENCES `{$prefix}blogs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ap_notifications' => "CREATE TABLE IF NOT EXISTS `{$prefix}ap_notifications` (
              `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `blog_id`      INT UNSIGNED NOT NULL,
              `post_id`      INT UNSIGNED DEFAULT NULL,
              `parent_id`    INT UNSIGNED DEFAULT NULL,
              `type`         VARCHAR(32) NOT NULL,
              `actor_id`     VARCHAR(500) NOT NULL,
              `handle`       VARCHAR(191) DEFAULT NULL,
              `display_name` VARCHAR(200) DEFAULT NULL,
              `avatar_url`   VARCHAR(500) DEFAULT NULL,
              `content`      MEDIUMTEXT,
              `activity_id`  VARCHAR(500) DEFAULT NULL,
              `object_id`    VARCHAR(500) DEFAULT NULL,
              `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
              `deleted_at`   DATETIME DEFAULT NULL,
              `created_at`   DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_ap_notifications_activity` (`blog_id`,`activity_id`(191)),
              KEY `ix_ap_notifications_blog` (`blog_id`,`is_read`,`created_at`),
              KEY `ix_ap_notifications_parent` (`blog_id`,`parent_id`),
              KEY `ix_ap_notifications_type` (`blog_id`,`type`,`created_at`),
              CONSTRAINT `fk_ap_notifications_blog` FOREIGN KEY (`blog_id`)
                REFERENCES `{$prefix}blogs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ap_deliveries' => "CREATE TABLE IF NOT EXISTS `{$prefix}ap_deliveries` (
              `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `blog_id`         INT UNSIGNED NOT NULL,
              `inbox_url`       VARCHAR(500) NOT NULL,
              `payload`         MEDIUMTEXT NOT NULL,
              `attempts`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `last_error`      VARCHAR(300) DEFAULT NULL,
              `next_attempt_at` DATETIME NOT NULL,
              `created_at`      DATETIME NOT NULL,
              PRIMARY KEY (`id`),
              KEY `ix_ap_deliveries_due` (`next_attempt_at`),
              KEY `ix_ap_deliveries_blog` (`blog_id`),
              CONSTRAINT `fk_ap_deliveries_blog` FOREIGN KEY (`blog_id`)
                REFERENCES `{$prefix}blogs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'ap_remote_actors' => "CREATE TABLE IF NOT EXISTS `{$prefix}ap_remote_actors` (
              `actor_id`         VARCHAR(500) NOT NULL,
              `inbox_url`        VARCHAR(500) NOT NULL,
              `shared_inbox_url` VARCHAR(500) DEFAULT NULL,
              `public_key`       TEXT,
              `handle`           VARCHAR(191) DEFAULT NULL,
              `display_name`     VARCHAR(200) DEFAULT NULL,
              `avatar_url`       VARCHAR(500) DEFAULT NULL,
              `fetched_at`       DATETIME NOT NULL,
              PRIMARY KEY (`actor_id`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $table => $sql) {
            if (!$db->tableExists($table)) {
                $db->pdo()->exec($sql);
            }
        }
    }

    private static function ensureNotificationParent(Database $db): void
    {
        if (!$db->tableExists('ap_notifications') || $db->columnExists('ap_notifications', 'parent_id')) {
            return;
        }

        try {
            $db->query(
                'ALTER TABLE {{ap_notifications}}
                 ADD COLUMN `parent_id` INT UNSIGNED DEFAULT NULL AFTER `post_id`'
            );
        } catch (\Throwable $e) {
            if (!$db->columnExists('ap_notifications', 'parent_id')) {
                throw $e;
            }
        }

        try {
            $db->query(
                'ALTER TABLE {{ap_notifications}}
                 ADD KEY `ix_ap_notifications_parent` (`blog_id`,`parent_id`)'
            );
        } catch (\Throwable) {
            // L'indice c'è già, o il motore non lo accetta: si va avanti.
        }
    }

    private static function ensureNotificationDeleted(Database $db): void
    {
        if (!$db->tableExists('ap_notifications') || $db->columnExists('ap_notifications', 'deleted_at')) {
            return;
        }

        try {
            $db->query(
                'ALTER TABLE {{ap_notifications}}
                 ADD COLUMN `deleted_at` DATETIME DEFAULT NULL AFTER `is_read`'
            );
        } catch (\Throwable $e) {
            if (!$db->columnExists('ap_notifications', 'deleted_at')) {
                throw $e;
            }
        }
    }
}
