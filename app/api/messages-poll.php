<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/conversation-access.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

$user_id   = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$account_status = $stmt->fetchColumn();
if ($account_status === false || ($account_status !== null && $account_status !== '' && $account_status !== 'active')) {
    echo json_encode(['error' => 'compte_restreint']);
    exit;
}

$sortie_id = isset($_GET['sortie']) ? (int) $_GET['sortie'] : 0;
$other_id  = isset($_GET['user'])   ? (int) $_GET['user']   : 0;
$last_id   = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

if (!$sortie_id || !$other_id) {
    echo json_encode(['error' => 'params_manquants']);
    exit;
}

// Vérification accès identique à conversation.php
if (!conversation_autorisee($pdo, $sortie_id, $user_id, $other_id)) {
    echo json_encode(['error' => 'acces_refuse']);
    exit;
}

// Marquer comme lus les messages reçus
$stmt = $pdo->prepare("
    UPDATE messages SET lu = 1
    WHERE sortie_id = ? AND expediteur_id = ? AND destinataire_id = ? AND lu = 0
");
$stmt->execute([$sortie_id, $other_id, $user_id]);

// Récupérer uniquement les messages après last_id
$stmt = $pdo->prepare("
    SELECT m.id, m.expediteur_id, m.contenu, m.created_at, u.prenom
    FROM messages m
    JOIN users u ON m.expediteur_id = u.id
    WHERE m.sortie_id = ?
    AND (
        (m.expediteur_id = ? AND m.destinataire_id = ?)
        OR
        (m.expediteur_id = ? AND m.destinataire_id = ?)
    )
    AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$sortie_id, $user_id, $other_id, $other_id, $user_id, $last_id]);
$nouveaux = $stmt->fetchAll();

$response = [];
foreach ($nouveaux as $msg) {
    $response[] = [
        'id'           => (int) $msg['id'],
        'expediteur_id' => (int) $msg['expediteur_id'],
        'contenu'      => $msg['contenu'],
        'created_at'   => $msg['created_at'],
        'heure'        => date('d/m à H:i', strtotime($msg['created_at'])),
        'moi'          => (int)$msg['expediteur_id'] === $user_id,
    ];
}

echo json_encode($response);
