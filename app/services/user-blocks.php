<?php
declare(strict_types=1);

function utilisateur_bloque(PDO $pdo, int $user_id, int $other_id): bool
{
    if ($user_id <= 0 || $other_id <= 0 || $user_id === $other_id) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_blocks
        WHERE (blocker_id = ? AND blocked_id = ?)
        OR (blocker_id = ? AND blocked_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$user_id, $other_id, $other_id, $user_id]);

    return (bool) $stmt->fetchColumn();
}

function utilisateur_bloque_par_moi(PDO $pdo, int $user_id, int $blocked_id): bool
{
    if ($user_id <= 0 || $blocked_id <= 0 || $user_id === $blocked_id) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_blocks
        WHERE blocker_id = ? AND blocked_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $blocked_id]);

    return (bool) $stmt->fetchColumn();
}
