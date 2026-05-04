ALTER TABLE users
  ADD COLUMN IF NOT EXISTS age_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS age_verified_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS verification_provider VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS verification_reference_id VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS verification_status VARCHAR(30) NOT NULL DEFAULT 'not_started',
  ADD COLUMN IF NOT EXISTS verification_expires_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS adult_access_revoked_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS safety_onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS safety_onboarding_completed_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS account_safety_status VARCHAR(30) NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS age_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(80) NOT NULL,
  provider_reference_id VARCHAR(120) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  age_verified TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_age_verifications_user_status (user_id, status),
  KEY idx_age_verifications_reference (provider_reference_id)
);

CREATE TABLE IF NOT EXISTS user_profiles_intime (
  user_id INT PRIMARY KEY,
  pseudo VARCHAR(80) NOT NULL,
  age_range VARCHAR(30) NULL,
  zone_approximative VARCHAR(120) NULL,
  rencontre_preferences VARCHAR(255) NULL,
  disponibilites VARCHAR(255) NULL,
  limites_attentes TEXT NULL,
  bio_courte TEXT NULL,
  photo_visibility VARCHAR(40) NOT NULL DEFAULT 'verified_users_only',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS proposals_intime (
  id INT AUTO_INCREMENT PRIMARY KEY,
  creator_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  intention VARCHAR(255) NOT NULL,
  location_scope VARCHAR(30) NOT NULL DEFAULT 'city',
  approximate_location VARCHAR(120) NOT NULL,
  time_window VARCHAR(120) NOT NULL,
  visibility VARCHAR(40) NOT NULL DEFAULT 'verified_users_only',
  limits_expectations TEXT NULL,
  consent_rules_accepted TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_proposals_intime_creator (creator_id),
  KEY idx_proposals_intime_status_expires (status, expires_at)
);

CREATE TABLE IF NOT EXISTS intime_interests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  user_id INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'interested',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_intime_interest (proposal_id, user_id),
  KEY idx_intime_interests_user (user_id)
);

CREATE TABLE IF NOT EXISTS intime_matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT NOT NULL,
  creator_id INT NOT NULL,
  interested_user_id INT NOT NULL,
  creator_confirmed_at DATETIME NULL,
  interested_confirmed_at DATETIME NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_intime_match (proposal_id, creator_id, interested_user_id),
  KEY idx_intime_matches_users (creator_id, interested_user_id, status)
);

CREATE TABLE IF NOT EXISTS privacy_settings (
  user_id INT PRIMARY KEY,
  show_city_only TINYINT(1) NOT NULL DEFAULT 1,
  hide_from_search TINYINT(1) NOT NULL DEFAULT 0,
  intimate_photo_visibility VARCHAR(40) NOT NULL DEFAULT 'verified_users_only',
  allow_intime_notifications TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS consent_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(80) NOT NULL,
  version VARCHAR(30) NOT NULL,
  accepted TINYINT(1) NOT NULL,
  ip_hash VARCHAR(64) NULL,
  user_agent_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_consent_events_user_type (user_id, type, created_at)
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(60) NULL,
  target_id INT NULL,
  details VARCHAR(255) NULL,
  ip_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_logs_actor_created (actor_user_id, created_at),
  KEY idx_audit_logs_target (target_type, target_id)
);
