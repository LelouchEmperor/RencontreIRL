ALTER TABLE user_profiles_intime
  ADD COLUMN IF NOT EXISTS intentions VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS preferred_first_step VARCHAR(80) NOT NULL DEFAULT 'social_first';

CREATE TABLE IF NOT EXISTS intime_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_one_id INT NOT NULL,
  user_two_id INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_intime_conversation_pair (user_one_id, user_two_id),
  KEY idx_intime_conversations_user_one (user_one_id, status),
  KEY idx_intime_conversations_user_two (user_two_id, status)
);

CREATE TABLE IF NOT EXISTS intime_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  expediteur_id INT NOT NULL,
  contenu TEXT NOT NULL,
  lu TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_intime_messages_conversation_created (conversation_id, created_at),
  KEY idx_intime_messages_expediteur_created (expediteur_id, created_at)
);
