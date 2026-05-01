<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('app/pages/sorties.php'));
    exit;
}

csrf_verify();

$user_id = (int) $_SESSION['user_id'];
$target_user_id = (int) ($_POST['target_user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? app_url('app/pages/sorties.php');

if ($target_user_id <= 0 || $target_user_id === $user_id) {
    header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
    exit;
}

if ($action === 'block') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $target_user_id]);
}

if ($action === 'unblock') {
    $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$user_id, $target_user_id]);
}

header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
exit;
