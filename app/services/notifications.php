<?php
declare(strict_types=1);

function creer_notification(PDO $pdo, int $user_id, string $type, string $message, string $lien): void
{
    if ($user_id <= 0 || trim($message) === '') {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, lien)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $type, texte_court($message, 180), $lien]);
}

function notifier_participants_sortie(
    PDO $pdo,
    int $sortie_id,
    string $type,
    string $message,
    array $exclure_user_ids = []
): void {
    $exclusions = array_fill_keys(array_map('intval', $exclure_user_ids), true);

    $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM participations WHERE sortie_id = ?");
    $stmt->execute([$sortie_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $participant_id) {
        $participant_id = (int) $participant_id;

        if (isset($exclusions[$participant_id])) {
            continue;
        }

        creer_notification(
            $pdo,
            $participant_id,
            $type,
            $message,
            'app/pages/sortie.php?id=' . $sortie_id
        );
    }
}
