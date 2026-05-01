<?php
declare(strict_types=1);

function supprimer_utilisateur_complet(PDO $pdo, int $user_id): array
{
    if ($user_id <= 0) {
        throw new InvalidArgumentException('Utilisateur invalide.');
    }

    $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Utilisateur introuvable.');
    }

    $stmt = $pdo->prepare("SELECT nom_fichier FROM photos_profil WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $photos_a_supprimer = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($user['photo'])) {
        $photos_a_supprimer[] = $user['photo'];
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE sorties s
            JOIN participations p ON p.sortie_id = s.id
            SET s.places_restantes = LEAST(s.places_total, s.places_restantes + 1)
            WHERE p.user_id = ?
        ");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM likes_sorties
            WHERE user_id = ?
            OR sortie_id IN (SELECT id FROM sorties WHERE user_id = ?)
        ");
        $stmt->execute([$user_id, $user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM participations
            WHERE user_id = ?
            OR sortie_id IN (SELECT id FROM sorties WHERE user_id = ?)
        ");
        $stmt->execute([$user_id, $user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM sortie_reviews
            WHERE reviewer_id = ?
            OR reviewed_id = ?
            OR sortie_id IN (SELECT id FROM sorties WHERE user_id = ?)
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM reports
            WHERE target_type = 'message'
            AND target_id IN (
                SELECT id
                FROM messages
                WHERE expediteur_id = ?
                OR destinataire_id = ?
                OR sortie_id IN (SELECT id FROM sorties WHERE user_id = ?)
            )
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM messages
            WHERE expediteur_id = ?
            OR destinataire_id = ?
            OR sortie_id IN (SELECT id FROM sorties WHERE user_id = ?)
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);

        $stmt = $pdo->prepare("
            DELETE FROM reports
            WHERE reporter_id = ?
            OR reviewed_by = ?
            OR (target_type = 'user' AND target_id = ?)
            OR (target_type = 'sortie' AND target_id IN (SELECT id FROM sorties WHERE user_id = ?))
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?");
        $stmt->execute([$user_id, $user_id]);

        $stmt = $pdo->prepare("DELETE FROM reponses_prompts WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM photos_profil WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM sorties WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    return array_values(array_unique(array_filter($photos_a_supprimer)));
}

function supprimer_fichiers_upload(array $noms_fichiers): void
{
    foreach ($noms_fichiers as $nom_fichier) {
        $chemin = chemin_upload((string) $nom_fichier);
        if ($chemin && is_file($chemin)) {
            unlink($chemin);
        }
    }
}
