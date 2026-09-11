-- Noblogs — schema del database
-- Le occorrenze di {{prefix}} vengono sostituite dall'installer con il prefisso
-- scelto (vuoto per default). MySQL 5.7+ / MariaDB 10.2+, InnoDB, utf8mb4.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- Utenti
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`             VARCHAR(191) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `role`              ENUM('user','moderator','admin') NOT NULL DEFAULT 'user',
  `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
  `max_blogs`         SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  `locale`            VARCHAR(10) NOT NULL DEFAULT 'it',
  `timezone`          VARCHAR(64) NOT NULL DEFAULT 'Europe/Rome',
  `email_verified_at` DATETIME DEFAULT NULL,
  `verify_token`      VARCHAR(64) DEFAULT NULL,
  `reset_token`       VARCHAR(64) DEFAULT NULL,
  `reset_expires_at`  DATETIME DEFAULT NULL,
  `dashboard_css`     MEDIUMTEXT,
  `created_at`        DATETIME NOT NULL,
  `last_login_at`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `ix_users_verify` (`verify_token`),
  KEY `ix_users_reset` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Blog (tenant)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}blogs` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL,
  `subdomain`         VARCHAR(63) NOT NULL,
  `domain`            VARCHAR(191) DEFAULT NULL,
  `title`             VARCHAR(200) NOT NULL,
  `content`           MEDIUMTEXT,
  `nav`               TEXT,
  `meta_description`  VARCHAR(300) DEFAULT NULL,
  `meta_image`        VARCHAR(300) DEFAULT NULL,
  `favicon`           VARCHAR(300) NOT NULL DEFAULT '🌐',
  `lang`              VARCHAR(10) NOT NULL DEFAULT 'it',
  `blog_path`         VARCHAR(100) NOT NULL DEFAULT 'blog',
  `theme`             VARCHAR(100) NOT NULL DEFAULT 'default',
  `custom_css`        MEDIUMTEXT,
  `overwrite_styles`  TINYINT(1) NOT NULL DEFAULT 0,
  `header_directive`  MEDIUMTEXT,
  `footer_directive`  MEDIUMTEXT,
  `date_format`       VARCHAR(32) NOT NULL DEFAULT 'j M Y',
  `post_template`     MEDIUMTEXT,
  `robots_txt`        TEXT,
  `rss_alias`         VARCHAR(100) DEFAULT NULL,
  `all_tags`          TEXT,
  `analytics_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `upvotes_active`    TINYINT(1) NOT NULL DEFAULT 1,
  -- Salta la bonifica dell'HTML nei contenuti. Lo concede l'amministrazione,
  -- non l'autore: chi scrive HTML libero su un sottodominio può costruirci
  -- una pagina di raccolta credenziali.
  `allow_raw_html`    TINYINT(1) NOT NULL DEFAULT 0,
  `subscriptions_active` TINYINT(1) NOT NULL DEFAULT 0,
  `discoverable`      TINYINT(1) NOT NULL DEFAULT 1,
  `reviewed`          TINYINT(1) NOT NULL DEFAULT 0,
  -- 1 = indirizzo pubblico sul terzo livello (nome.dominio). 0 = solo
  -- percorso (dominio/nome). Lo decide l'amministrazione, non l'approvazione.
  `use_subdomain`     TINYINT(1) NOT NULL DEFAULT 0,
  `hidden`            TINYINT(1) NOT NULL DEFAULT 0,
  `flagged`           TINYINT(1) NOT NULL DEFAULT 0,
  `to_review`         TINYINT(1) NOT NULL DEFAULT 0,
  `dodginess_score`   FLOAT NOT NULL DEFAULT 0,
  `reviewer_note`     TEXT,
  `storage_used`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `posts_last_12h`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL,
  `updated_at`        DATETIME NOT NULL,
  `last_posted_at`    DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blogs_subdomain` (`subdomain`),
  UNIQUE KEY `uq_blogs_domain` (`domain`),
  KEY `ix_blogs_user` (`user_id`),
  KEY `ix_blogs_discovery` (`reviewed`,`hidden`,`discoverable`),
  KEY `ix_blogs_review_queue` (`to_review`,`dodginess_score`),
  CONSTRAINT `fk_blogs_user` FOREIGN KEY (`user_id`)
    REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Post e pagine (stessa tabella, distinte da is_page)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}posts` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`           INT UNSIGNED NOT NULL,
  `uid`               VARCHAR(32) NOT NULL,
  `title`             VARCHAR(200) NOT NULL,
  `slug`              VARCHAR(200) NOT NULL,
  `alias`             VARCHAR(200) DEFAULT NULL,
  `content`           MEDIUMTEXT,
  `content_length`    INT UNSIGNED NOT NULL DEFAULT 0,
  `is_page`           TINYINT(1) NOT NULL DEFAULT 0,
  `is_published`      TINYINT(1) NOT NULL DEFAULT 1,
  `make_discoverable` TINYINT(1) NOT NULL DEFAULT 1,
  `hidden`            TINYINT(1) NOT NULL DEFAULT 0,
  `tags`              TEXT,
  `canonical_url`     VARCHAR(300) DEFAULT NULL,
  `meta_description`  VARCHAR(300) DEFAULT NULL,
  `meta_image`        VARCHAR(300) DEFAULT NULL,
  `lang`              VARCHAR(10) DEFAULT NULL,
  `class_name`        VARCHAR(200) DEFAULT NULL,
  `upvotes`           INT NOT NULL DEFAULT 0,
  `shadow_votes`      INT NOT NULL DEFAULT 0,
  `score`             DOUBLE NOT NULL DEFAULT 0,
  `published_at`      DATETIME NOT NULL,
  `first_published_at` DATETIME DEFAULT NULL,
  `created_at`        DATETIME NOT NULL,
  `updated_at`        DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_uid` (`uid`),
  UNIQUE KEY `uq_posts_blog_slug` (`blog_id`,`slug`),
  KEY `ix_posts_listing` (`blog_id`,`is_page`,`is_published`,`published_at`),
  KEY `ix_posts_alias` (`blog_id`,`alias`),
  KEY `ix_posts_discovery` (`is_published`,`make_discoverable`,`hidden`,`score`),
  CONSTRAINT `fk_posts_blog` FOREIGN KEY (`blog_id`)
    REFERENCES `{{prefix}}blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Analytics. Nessun IP, nessuno user agent grezzo, nessun identificativo
-- persistente: hash_id = sha256(ip + data + salt) e cambia ogni giorno.
-- post_id vale 0 per la homepage, così la chiave unica funziona (in MySQL i
-- NULL non collidono mai in un indice unico).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}hits` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`     INT UNSIGNED NOT NULL,
  `post_id`     INT UNSIGNED NOT NULL DEFAULT 0,
  `hash_id`     CHAR(64) NOT NULL,
  `hit_date`    DATE NOT NULL,
  `created_at`  DATETIME NOT NULL,
  `referrer`    VARCHAR(191) DEFAULT NULL,
  `country`     VARCHAR(2) DEFAULT NULL,
  `device`      VARCHAR(32) DEFAULT NULL,
  `browser`     VARCHAR(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hits_unique_read` (`blog_id`,`post_id`,`hash_id`,`hit_date`),
  KEY `ix_hits_blog_date` (`blog_id`,`hit_date`),
  KEY `ix_hits_post_date` (`post_id`,`hit_date`),
  KEY `ix_hits_recent` (`blog_id`,`created_at`),
  CONSTRAINT `fk_hits_blog` FOREIGN KEY (`blog_id`)
    REFERENCES `{{prefix}}blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Upvote: un voto per identità (hash annuale) per post
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}upvotes` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id`     INT UNSIGNED NOT NULL,
  `hash_id`     CHAR(64) NOT NULL,
  `marked`      TINYINT(1) NOT NULL DEFAULT 0,
  `signals`     VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_upvotes_post_hash` (`post_id`,`hash_id`),
  CONSTRAINT `fk_upvotes_post` FOREIGN KEY (`post_id`)
    REFERENCES `{{prefix}}posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Iscritti alla newsletter di un blog (double opt-in)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}subscribers` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`       INT UNSIGNED NOT NULL,
  `email`         VARCHAR(191) NOT NULL,
  `confirmed`     TINYINT(1) NOT NULL DEFAULT 0,
  `token`         VARCHAR(64) NOT NULL,
  `created_at`    DATETIME NOT NULL,
  `confirmed_at`  DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscribers_blog_email` (`blog_id`,`email`),
  KEY `ix_subscribers_token` (`token`),
  CONSTRAINT `fk_subscribers_blog` FOREIGN KEY (`blog_id`)
    REFERENCES `{{prefix}}blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- File caricati
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}media` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`     INT UNSIGNED NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `path`        VARCHAR(400) NOT NULL,
  `mime`        VARCHAR(100) NOT NULL,
  `size`        INT UNSIGNED NOT NULL DEFAULT 0,
  `width`       SMALLINT UNSIGNED DEFAULT NULL,
  `height`      SMALLINT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_media_blog_path` (`blog_id`,`path`),
  KEY `ix_media_blog` (`blog_id`,`created_at`),
  CONSTRAINT `fk_media_blog` FOREIGN KEY (`blog_id`)
    REFERENCES `{{prefix}}blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Redirect definiti dall'utente (oltre agli alias dei singoli post)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}redirects` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`     INT UNSIGNED NOT NULL,
  `from_path`   VARCHAR(200) NOT NULL,
  `to_url`      VARCHAR(400) NOT NULL,
  `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 302,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirects_blog_from` (`blog_id`,`from_path`),
  CONSTRAINT `fk_redirects_blog` FOREIGN KEY (`blog_id`)
    REFERENCES `{{prefix}}blogs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Temi della piattaforma
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}themes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(100) NOT NULL,
  `title`       VARCHAR(120) NOT NULL,
  `description` VARCHAR(300) DEFAULT NULL,
  `css`         MEDIUMTEXT,
  `sort_order`  SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_themes_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Impostazioni della piattaforma (chiave/valore)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}settings` (
  `name`  VARCHAR(100) NOT NULL,
  `value` MEDIUMTEXT,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Rate limiting e registro di moderazione
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `{{prefix}}rate_limits` (
  `bucket`      VARCHAR(191) NOT NULL,
  `counter`     INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`  DATETIME NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `ix_rate_limits_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}moderation_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_id`     INT UNSIGNED DEFAULT NULL,
  `actor_id`    INT UNSIGNED DEFAULT NULL,
  `action`      VARCHAR(64) NOT NULL,
  `note`        TEXT,
  `created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_modlog_blog` (`blog_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
