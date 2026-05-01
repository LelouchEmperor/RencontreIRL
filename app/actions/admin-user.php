<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../services/user-cleanup.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (empty($admin['is_admin'])) {
    http_response_code(403);
    die('Acces refuse.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('app/pages/sorties.php'));
    exit;
}

csrf_verify();

$target_user_id = (int) ($_POST['target_user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? app_url('app/pages/sorties.php');
$redirect_after_delete = $_POST['redirect_after_delete'] ?? $redirect;

if ($target_user_id <= 0 || $target_user_id === $admin_id) {
    header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
    exit;
}

if ($action === 'set_status') {
    $account_status = $_POST['account_status'] ?? '';
    $allowed_statuses = ['active', 'suspended', 'banned'];

    if (in_array($account_status, $allowed_statuses, true)) {
        $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ?");
        $stmt->execute([$account_status, $target_user_id]);
    }

    header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
    exit;
}

if ($action === 'delete_user') {
    try {
        $photos = supprimer_utilisateur_complet($pdo, $target_user_id);
        supprimer_fichiers_upload($photos);
    } catch (Throwable $e) {
        header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
        exit;
    }

    header('Location: ' . lien_interne($redirect_after_delete, 'app/admin/users.php'));
    exit;
}

header('Location: ' . lien_interne($redirect, 'app/pages/sorties.php'));
exit;
