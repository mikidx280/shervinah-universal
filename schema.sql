-- Shervinah Universal — database schema
--
-- Run this once against the MySQL/MariaDB database used by private-config.php
-- (db_host / db_name / db_user / db_pass) before the lead forms or the admin
-- dashboard can work. Safe to re-run: uses IF NOT EXISTS.
--
-- Example (phpMyAdmin in hPanel, or the mysql CLI):
--   mysql -u <db_user> -p <db_name> < schema.sql

CREATE TABLE IF NOT EXISTS su_leads (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_type          VARCHAR(32)  NOT NULL,
  full_name          VARCHAR(180) NOT NULL DEFAULT '',
  email              VARCHAR(190) NOT NULL,
  phone              VARCHAR(60)  NOT NULL DEFAULT '',
  language           VARCHAR(10)  NOT NULL DEFAULT 'en',
  marketing_consent  TINYINT(1)   NOT NULL DEFAULT 0,
  service_consent    TINYINT(1)   NOT NULL DEFAULT 0,
  payload_json       TEXT         NULL,
  consent_version    VARCHAR(32)  NOT NULL DEFAULT '',
  ip_hash            CHAR(64)     NOT NULL DEFAULT '',
  status             VARCHAR(20)  NOT NULL DEFAULT 'new',
  created_at         DATETIME     NOT NULL,
  updated_at         DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_form_type (form_type),
  KEY idx_created_at (created_at),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Column notes:
--   form_type       one of: club, course, collaboration, personal_message, contact
--                   (see $allowedTypes in api/submit.php and admin/index.php)
--   payload_json    raw JSON of any extra form-specific fields (e.g. country,
--                   birth_date, request_area) that don't have their own column
--   ip_hash         sha256 HMAC of the submitter's IP (see ip_salt in
--                   private-config.php) — stored instead of the raw IP so the
--                   database never holds a directly identifying address
--   status          new / contacted / completed (matches the admin dashboard's
--                   status <select> in admin/index.php)
