CREATE TABLE IF NOT EXISTS sortie_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sortie_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewed_id INT NOT NULL,
    note TINYINT NOT NULL,
    commentaire TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_sortie_review_pair (sortie_id, reviewer_id, reviewed_id),
    KEY idx_sortie_reviews_reviewed (reviewed_id, created_at),
    KEY idx_sortie_reviews_sortie (sortie_id),
    CONSTRAINT chk_sortie_reviews_note CHECK (note BETWEEN 1 AND 5)
);
