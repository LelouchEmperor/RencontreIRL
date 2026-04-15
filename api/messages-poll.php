<?php
require_once '../includes/header.php';
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'non_connecte']);
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$sortie_id = isset($_GET['sortie']) ? (int) $_GET['sortie'] : 0;
$other_id  = isset($_GET['user'])   ? (int) $_GET['user']   : 0;
$last_id   = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

if (!$sortie_id || !$other_id) {
    echo json_encode(['error' => 'params_manquants']);
    exit;
}

// Vérification accès identique à conversation.php
$stmt = $pdo->prepare("SELECT user_id FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if (!$sortie) {
    echo json_encode(['error' => 'sortie_introuvable']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN p.user_id = ? THEN 1 ELSE 0 END) as user_est_participant,
        SUM(CASE WHEN p.user_id = ? THEN 1 ELSE 0 END) as other_est_participant
    FROM participations p
    WHERE p.sortie_id = ?
");
$stmt->execute([$user_id, $other_id, $sortie_id]);
$acces = $stmt->fetch();

$user_a_acces  = $acces['user_est_participant'] > 0 || (int)$sortie['user_id'] === $user_id;
$other_a_acces = $acces['other_est_participant'] > 0 || (int)$sortie['user_id'] === $other_id;

if (!$user_a_acces || !$other_a_acces) {
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