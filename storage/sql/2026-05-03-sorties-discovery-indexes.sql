CREATE INDEX IF NOT EXISTS idx_sorties_discovery_status_date ON sorties (status, date_sortie);
CREATE INDEX IF NOT EXISTS idx_sorties_discovery_ville_date ON sorties (ville, date_sortie);
CREATE INDEX IF NOT EXISTS idx_sorties_discovery_activite_date ON sorties (activite, date_sortie);
CREATE INDEX IF NOT EXISTS idx_sorties_discovery_places_date ON sorties (places_restantes, date_sortie);
CREATE INDEX IF NOT EXISTS idx_sorties_discovery_created ON sorties (created_at);
CREATE INDEX IF NOT EXISTS idx_likes_sorties_sortie_created ON likes_sorties (sortie_id, created_at);
