<?php
require_once 'config/db.php';
require_once 'includes/header.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['sortie_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$sortie_id = (int) $_POST['sortie_id'];

$stmt = $pdo->prepare("SELECT id FROM likes_sorties WHERE user_id = ? AND sortie_id = ?");
$stmt->execute([$user_id, $sortie_id]);
$existe = $stmt->fetch();

if ($existe) {
    $pdo->prepare("DELETE FROM likes_sorties WHERE user_id = ? AND sortie_id = ?")->execute([$user_id, $sortie_id]);
} else {
    $pdo->prepare("INSERT INTO likes_sorties (user_id, sortie_id) VALUES (?, ?)")->execute([$user_id, $sortie_id]);
}
if (isset($_POST['ajax'])) {
    exit;
}
header('Location: /Site_rencontre/RencontreIRL/sorties.php');
exit;