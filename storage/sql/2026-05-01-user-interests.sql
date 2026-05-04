CREATE TABLE IF NOT EXISTS user_interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    interest VARCHAR(60) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_interests_user_interest (user_id, interest),
    KEY idx_user_interests_interest (interest),
    CONSTRAINT fk_user_interests_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);
