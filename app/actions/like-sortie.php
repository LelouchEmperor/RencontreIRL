<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['sortie_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT account_status FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$account_status = $stmt->fetchColumn();
if ($account_status === false || ($account_status !== null && $account_status !== '' && $account_status !== 'active')) {
    http_response_code(403);
    exit;
}

csrf_verify(false);

$user_id   = $_SESSION['user_id'];
$sortie_id = (int) $_POST['sortie_id'];

$stmt = $pdo->prepare("
    SELECT s.id, s.status, s.date_sortie, s.places_restantes, u.account_status AS organisateur_status
    FROM sorties s
    JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if (!$sortie || !in_array(($sortie['organisateur_status'] ?? 'active') ?: 'active', ['', 'active'], true) || sortie_statut_effectif($sortie) !== 'open') {
    http_response_code(403);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM likes_sorties WHERE user_id = ? AND sortie_id = ?");
$stmt->execute([$user_id, $sortie_id]);
$existe = $stmt->fetch();

if ($existe) {
    $pdo->prepare("DELETE FROM likes_sorties WHERE user_id = ? AND sortie_id = ?")->execute([$user_id, $sortie_id]);
} else {
    $pdo->prepare("INSERT IGNORE INTO likes_sorties (user_id, sortie_id) VALUES (?, ?)")->execute([$user_id, $sortie_id]);
}
if (isset($_POST['ajax'])) {
    exit;
}
header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
exit;
