CREATE TABLE IF NOT EXISTS intime_profile_interests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  target_user_id INT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'interested',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_intime_profile_interest (requester_id, target_user_id),
  KEY idx_intime_profile_interests_target (target_user_id, status, created_at)
);
