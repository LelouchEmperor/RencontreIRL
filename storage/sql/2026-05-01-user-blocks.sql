CREATE TABLE IF NOT EXISTS user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_blocks_pair (blocker_id, blocked_id),
    KEY idx_user_blocks_blocker (blocker_id),
    KEY idx_user_blocks_blocked (blocked_id)
);
