-- Contraintes et index de securite/coherence pour RencontreIRL.
-- A executer manuellement dans phpMyAdmin apres verification des doublons existants.

-- Nettoyage indicatif avant ajout des contraintes :
-- SELECT sortie_id, user_id, COUNT(*) FROM participations GROUP BY sortie_id, user_id HAVING COUNT(*) > 1;
-- SELECT user_id, sortie_id, COUNT(*) FROM likes_sorties GROUP BY user_id, sortie_id HAVING COUNT(*) > 1;

ALTER TABLE participations
    ADD CONSTRAINT uniq_participation_sortie_user UNIQUE (sortie_id, user_id);

ALTER TABLE likes_sorties
    ADD CONSTRAINT uniq_like_user_sortie UNIQUE (user_id, sortie_id);

ALTER TABLE sorties
    ADD CONSTRAINT chk_sorties_places
    CHECK (places_total >= 0 AND places_restantes >= 0 AND places_restantes <= places_total);

CREATE INDEX idx_messages_destinataire_lu ON messages (destinataire_id, lu);
CREATE INDEX idx_messages_conversation ON messages (sortie_id, expediteur_id, destinataire_id, created_at);
CREATE INDEX idx_notifications_user_lu ON notifications (user_id, lu, created_at);
CREATE INDEX idx_sorties_date_places ON sorties (date_sortie, places_restantes);
CREATE INDEX idx_reports_status_created ON reports (status, created_at);
