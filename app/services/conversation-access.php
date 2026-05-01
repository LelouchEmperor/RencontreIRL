<?php
declare(strict_types=1);

require_once __DIR__ . '/user-blocks.php';

function conversation_autorisee(PDO $pdo, int $sortie_id, int $user_id, int $other_id): bool
{
    if ($sortie_id <= 0 || $user_id <= 0 || $other_id <= 0 || $user_id === $other_id) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT s.user_id, u.account_status AS organisateur_status
        FROM sorties s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = ?
    ");
    $stmt->execute([$sortie_id]);
    $sortie = $stmt->fetch();

    if (!$sortie) {
        return false;
    }

    if (!in_array(($sortie['organisateur_status'] ?? 'active') ?: 'active', ['', 'active'], true)) {
        return false;
    }

    if (utilisateur_bloque($pdo, $user_id, $other_id)) {
        return false;
    }

    $organisateur_id = (int) $sortie['user_id'];
    $user_est_organisateur = $organisateur_id === $user_id;
    $other_est_organisateur = $organisateur_id === $other_id;

    $stmt = $pdo->prepare("
        SELECT user_id
        FROM participations
        WHERE sortie_id = ?
        AND user_id IN (?, ?)
    ");
    $stmt->execute([$sortie_id, $user_id, $other_id]);
    $participants = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $user_est_participant = in_array($user_id, $participants, true);
    $other_est_participant = in_array($other_id, $participants, true);

    if ($other_est_organisateur) {
        return true;
    }

    if ($user_est_participant && $other_est_participant) {
        return true;
    }

    if (!$user_est_organisateur) {
        return false;
    }

    if ($other_est_participant) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM messages
        WHERE sortie_id = ?
        AND (
            (expediteur_id = ? AND destinataire_id = ?)
            OR
            (expediteur_id = ? AND destinataire_id = ?)
        )
        LIMIT 1
    ");
    $stmt->execute([$sortie_id, $user_id, $other_id, $other_id, $user_id]);

    return (bool) $stmt->fetchColumn();
}
