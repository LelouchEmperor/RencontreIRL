ALTER TABLE photos_profil
  ADD COLUMN IF NOT EXISTS moderation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS moderation_reason VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS moderated_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS moderated_by INT NULL;

UPDATE photos_profil
SET moderation_status = 'approved'
WHERE moderation_status IS NULL OR moderation_status = '';

CREATE INDEX IF NOT EXISTS idx_photos_profil_moderation ON photos_profil (moderation_status, created_at);
CREATE INDEX IF NOT EXISTS idx_photos_profil_user_status ON photos_profil (user_id, moderation_status, ordre);
