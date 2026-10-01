-- Baseline: the complete schema as of 2026-10-01, the starting point for `bin/theatrecms migrate`
-- on an empty database. Earlier migrations are in migrations/legacy/ for reference and never run.
--
-- Generated once, then maintained by hand like any other migration (it must not change once
-- released; schema changes go in new, later migrations):
--   * the users / users_* tables from vendor/delight-im/auth/Database/MySQL.sql (owned by
--     delight-im/auth; never alter them in later migrations);
--   * every core entity in src/Models, from Doctrine's SchemaTool (MySQL), except
--     schema_migrations, which `migrate` creates itself before running anything.
--
-- An existing install whose schema is already current runs `bin/theatrecms migrate --baseline`
-- once instead of applying this file.

-- delight-im/auth
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '0',
  `verified` tinyint unsigned NOT NULL DEFAULT '0',
  `resettable` tinyint unsigned NOT NULL DEFAULT '1',
  `roles_mask` int unsigned NOT NULL DEFAULT '0',
  `registered` int unsigned NOT NULL,
  `last_login` int unsigned DEFAULT NULL,
  `force_logout` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_2fa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `mechanism` tinyint unsigned NOT NULL,
  `seed` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` int unsigned NOT NULL,
  `expires_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_mechanism` (`user_id`,`mechanism`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `event_at` int unsigned NOT NULL,
  `event_type` varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `admin_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(49) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_json` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_at` (`event_at`),
  KEY `user_id_event_at` (`user_id`,`event_at`),
  KEY `user_id_event_type_event_at` (`user_id`,`event_type`,`event_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_confirmations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `email` varchar(249) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selector` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `email_expires` (`email`,`expires`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_otps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `mechanism` tinyint unsigned NOT NULL,
  `single_factor` tinyint unsigned NOT NULL DEFAULT '0',
  `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires_at` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_mechanism` (`user_id`,`mechanism`),
  KEY `selector_user_id` (`selector`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_remembered` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL,
  `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user` int unsigned NOT NULL,
  `selector` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_expires` (`user`,`expires`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users_throttling` (
  `bucket` varchar(44) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `tokens` float NOT NULL,
  `replenished_at` int unsigned NOT NULL,
  `expires_at` int unsigned NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `expires_at` (`expires_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core entities (src/Models)

CREATE TABLE content_meta (id INT AUTO_INCREMENT NOT NULL, content_type VARCHAR(32) NOT NULL, content_id INT NOT NULL, meta_key VARCHAR(191) NOT NULL, meta_value LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, modified_at DATETIME NOT NULL, UNIQUE INDEX uniq_content_meta_key (content_type, content_id, meta_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, ticket_url VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, title VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, production_id INT DEFAULT NULL, venue_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5387574A989D9B62 (slug), INDEX IDX_5387574AECC6147F (production_id), INDEX IDX_5387574A40A73EBA (venue_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE media (id INT AUTO_INCREMENT NOT NULL, media_type VARCHAR(20) NOT NULL, url VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(100) DEFAULT NULL, size_bytes INT DEFAULT NULL, width INT DEFAULT NULL, height INT DEFAULT NULL, alt_text VARCHAR(255) DEFAULT NULL, caption LONGTEXT DEFAULT NULL, uploaded_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE media_variants (id INT AUTO_INCREMENT NOT NULL, size_name VARCHAR(50) NOT NULL, url VARCHAR(255) NOT NULL, width INT NOT NULL, height INT NOT NULL, media_id INT NOT NULL, INDEX IDX_BCF97D53EA9FDD75 (media_id), UNIQUE INDEX media_variants_media_size_unique (media_id, size_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE menus (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, location VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, modified_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_727508CF5E9E89CB (location), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE menu_items (id INT AUTO_INCREMENT NOT NULL, position INT DEFAULT 0 NOT NULL, label_override VARCHAR(255) DEFAULT NULL, link_type VARCHAR(32) NOT NULL, target_id INT DEFAULT NULL, custom_url VARCHAR(2048) DEFAULT NULL, created_at DATETIME NOT NULL, modified_at DATETIME NOT NULL, menu_id INT NOT NULL, parent_id INT DEFAULT NULL, INDEX IDX_70B2CA2ACCD7E912 (menu_id), INDEX IDX_70B2CA2A727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE organizations (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, mission_statement LONGTEXT DEFAULT NULL, founded_year INT DEFAULT NULL, logo_url VARCHAR(255) DEFAULT NULL, website_url VARCHAR(255) DEFAULT NULL, social_links JSON DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_427C1C7F989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE pages (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, modified_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_2074E575989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE posts (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, slug VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, modified_at DATETIME NOT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_885DBAFA989D9B62 (slug), INDEX IDX_885DBAFA3569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE production_people (role_type VARCHAR(255) NOT NULL, role VARCHAR(255) DEFAULT NULL, position INT NOT NULL, production_id INT NOT NULL, person_id INT NOT NULL, INDEX IDX_E7B6FE7BECC6147F (production_id), INDEX IDX_E7B6FE7B217BBB47 (person_id), PRIMARY KEY (production_id, person_id, role_type)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE production_works (position INT NOT NULL, production_id INT NOT NULL, work_id INT NOT NULL, INDEX IDX_ECE4C675ECC6147F (production_id), INDEX IDX_ECE4C675BB3453DB (work_id), PRIMARY KEY (production_id, work_id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE seasons (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, overview LONGTEXT DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, slug VARCHAR(255) NOT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_B4F4301C989D9B62 (slug), INDEX IDX_B4F4301C3569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE sponsorships (id INT AUTO_INCREMENT NOT NULL, sponsor_id INT NOT NULL, season_id INT DEFAULT NULL, production_id INT DEFAULT NULL, INDEX IDX_9A7028CA12F7FB51 (sponsor_id), INDEX IDX_9A7028CA4EC001D1 (season_id), INDEX IDX_9A7028CAECC6147F (production_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE terms (id INT AUTO_INCREMENT NOT NULL, taxonomy VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(191) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, modified_at DATETIME NOT NULL, INDEX idx_terms_taxonomy (taxonomy), UNIQUE INDEX uniq_terms_taxonomy_slug (taxonomy, slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE term_relationships (id INT AUTO_INCREMENT NOT NULL, content_type VARCHAR(32) NOT NULL, content_id INT NOT NULL, position INT DEFAULT 0 NOT NULL, term_id INT NOT NULL, INDEX IDX_7775C75AE2C35FC (term_id), INDEX idx_term_rel_content (content_type, content_id), UNIQUE INDEX uniq_term_rel (term_id, content_type, content_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE venues (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) NOT NULL, city VARCHAR(255) NOT NULL, state VARCHAR(255) NOT NULL, postcode VARCHAR(255) NOT NULL, capacity INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, accessibility_info LONGTEXT DEFAULT NULL, website_url VARCHAR(255) DEFAULT NULL, map_url VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_652E22AD989D9B62 (slug), INDEX IDX_652E22AD3569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE works (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, synopsis LONGTEXT DEFAULT NULL, slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_F6E50243989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE people (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, biography LONGTEXT DEFAULT NULL, headshot_url VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_28166A26989D9B62 (slug), INDEX IDX_28166A263569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE productions (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, excerpt VARCHAR(255) DEFAULT NULL, opening DATE DEFAULT NULL, closing DATE DEFAULT NULL, runtime INT DEFAULT NULL, age_recommendation VARCHAR(255) DEFAULT NULL, content_advisory LONGTEXT DEFAULT NULL, promo_video_url VARCHAR(255) DEFAULT NULL, ticket_purchase_url VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, season_id INT NOT NULL, venue_id INT DEFAULT NULL, featured_image_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_BBD7C0C2989D9B62 (slug), INDEX IDX_BBD7C0C24EC001D1 (season_id), INDEX IDX_BBD7C0C240A73EBA (venue_id), INDEX IDX_BBD7C0C23569D950 (featured_image_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE sponsors (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, logo_url VARCHAR(255) DEFAULT NULL, website_url VARCHAR(255) DEFAULT NULL, slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_9A31550F989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE work_creators (work_creator_id INT AUTO_INCREMENT NOT NULL, role VARCHAR(255) NOT NULL, work_id INT NOT NULL, person_id INT NOT NULL, INDEX IDX_1ABC735FBB3453DB (work_id), INDEX IDX_1ABC735F217BBB47 (person_id), PRIMARY KEY (work_creator_id)) DEFAULT CHARACTER SET utf8mb4;
CREATE TABLE scheduled_task_runs (name VARCHAR(191) NOT NULL, last_started_at DATETIME DEFAULT NULL, last_finished_at DATETIME DEFAULT NULL, last_status VARCHAR(16) DEFAULT NULL, last_exit_code INT DEFAULT NULL, last_output LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4;
ALTER TABLE events ADD CONSTRAINT FK_5387574AECC6147F FOREIGN KEY (production_id) REFERENCES productions (id);
ALTER TABLE events ADD CONSTRAINT FK_5387574A40A73EBA FOREIGN KEY (venue_id) REFERENCES venues (id);
ALTER TABLE media_variants ADD CONSTRAINT FK_BCF97D53EA9FDD75 FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE;
ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2ACCD7E912 FOREIGN KEY (menu_id) REFERENCES menus (id);
ALTER TABLE menu_items ADD CONSTRAINT FK_70B2CA2A727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE;
ALTER TABLE posts ADD CONSTRAINT FK_885DBAFA3569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
ALTER TABLE production_people ADD CONSTRAINT FK_E7B6FE7BECC6147F FOREIGN KEY (production_id) REFERENCES productions (id);
ALTER TABLE production_people ADD CONSTRAINT FK_E7B6FE7B217BBB47 FOREIGN KEY (person_id) REFERENCES people (id);
ALTER TABLE production_works ADD CONSTRAINT FK_ECE4C675ECC6147F FOREIGN KEY (production_id) REFERENCES productions (id);
ALTER TABLE production_works ADD CONSTRAINT FK_ECE4C675BB3453DB FOREIGN KEY (work_id) REFERENCES works (id);
ALTER TABLE seasons ADD CONSTRAINT FK_B4F4301C3569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
ALTER TABLE sponsorships ADD CONSTRAINT FK_9A7028CA12F7FB51 FOREIGN KEY (sponsor_id) REFERENCES sponsors (id);
ALTER TABLE sponsorships ADD CONSTRAINT FK_9A7028CA4EC001D1 FOREIGN KEY (season_id) REFERENCES seasons (id);
ALTER TABLE sponsorships ADD CONSTRAINT FK_9A7028CAECC6147F FOREIGN KEY (production_id) REFERENCES productions (id);
ALTER TABLE term_relationships ADD CONSTRAINT FK_7775C75AE2C35FC FOREIGN KEY (term_id) REFERENCES terms (id) ON DELETE CASCADE;
ALTER TABLE venues ADD CONSTRAINT FK_652E22AD3569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
ALTER TABLE people ADD CONSTRAINT FK_28166A263569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
ALTER TABLE productions ADD CONSTRAINT FK_BBD7C0C24EC001D1 FOREIGN KEY (season_id) REFERENCES seasons (id);
ALTER TABLE productions ADD CONSTRAINT FK_BBD7C0C240A73EBA FOREIGN KEY (venue_id) REFERENCES venues (id);
ALTER TABLE productions ADD CONSTRAINT FK_BBD7C0C23569D950 FOREIGN KEY (featured_image_id) REFERENCES media (id);
ALTER TABLE work_creators ADD CONSTRAINT FK_1ABC735FBB3453DB FOREIGN KEY (work_id) REFERENCES works (id);
ALTER TABLE work_creators ADD CONSTRAINT FK_1ABC735F217BBB47 FOREIGN KEY (person_id) REFERENCES people (id);
