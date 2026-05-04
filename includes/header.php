<?php
require_once __DIR__ . '/../config/security.php';

demarrer_session_securisee();

if (session_expiree_par_inactivite()) {
    detruire_session_courante();
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$nb_notifs = 0;
$nb_msgs = 0;
$nb_reports = 0;
$is_admin_header = false;

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/db.php';

    $stmt_user = $pdo->prepare("SELECT account_status, is_admin, age_verified, verification_status FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $current_user = $stmt_user->fetch();
    $current_user_status = $current_user['account_status'] ?? false;

    if ($current_user_status === false || ($current_user_status !== null && $current_user_status !== '' && $current_user_status !== 'active')) {
        detruire_session_courante();
        header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
        exit;
    }

    $chemin_courant = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $identity_routes_autorisees = [
        '/app/intime/verification.php',
        '/app/auth/deconnexion.php',
        '/app/auth/verifier-email.php',
        '/app/legal/',
    ];
    $route_identite_autorisee = false;

    foreach ($identity_routes_autorisees as $route_autorisee) {
        if (str_contains($chemin_courant, $route_autorisee)) {
            $route_identite_autorisee = true;
            break;
        }
    }

    if (
        !$route_identite_autorisee
        && (empty($current_user['age_verified']) || ($current_user['verification_status'] ?? '') !== 'verified')
    ) {
        header('Location: /Site_rencontre/RencontreIRL/app/intime/verification.php');
        exit;
    }

    $is_admin_header = !empty($current_user['is_admin']);

    $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
    $stmt_notif->execute([$_SESSION['user_id']]);
    $nb_notifs = $stmt_notif->fetchColumn();

    $stmt_msg = $pdo->prepare("
        SELECT COUNT(*)
        FROM messages m
        LEFT JOIN user_blocks b1 ON b1.blocker_id = ? AND b1.blocked_id = m.expediteur_id
        LEFT JOIN user_blocks b2 ON b2.blocker_id = m.expediteur_id AND b2.blocked_id = ?
        WHERE m.destinataire_id = ?
        AND m.lu = 0
        AND b1.id IS NULL
        AND b2.id IS NULL
    ");
    $stmt_msg->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $nb_msgs = $stmt_msg->fetchColumn();

    if ($is_admin_header) {
        $stmt_reports = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'");
        $nb_reports = $stmt_reports->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rencontre — Kindle Bloom</title>
  <link rel="stylesheet" href="/Site_rencontre/RencontreIRL/public/assets/css/style.css" />
  <script src="/Site_rencontre/RencontreIRL/public/assets/js/main.js" defer></script>
</head>
<body>
<nav class="nav">
  <a href="/Site_rencontre/RencontreIRL/public/" class="nav-logo">Rencontre</a>
  <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Ouvrir le menu" />
  <label for="nav-toggle" class="nav-toggle-label" aria-hidden="true"><span></span><span></span><span></span></label>
  <div class="nav-links">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="/Site_rencontre/RencontreIRL/app/pages/profil.php">Mon profil</a>
      <a href="/Site_rencontre/RencontreIRL/app/pages/sorties.php">Sorties</a>
      <a href="/Site_rencontre/RencontreIRL/app/intime/">Intime</a>
      <a href="/Site_rencontre/RencontreIRL/app/pages/mes-sorties.php">Mes sorties</a>
      <a href="/Site_rencontre/RencontreIRL/app/pages/messages.php">Messages<?= $nb_msgs > 0 ? ' <span class="nav-badge">' . (int) $nb_msgs . '</span>' : '' ?></a>
      <a href="/Site_rencontre/RencontreIRL/app/pages/notifications.php">Notifications<?= $nb_notifs > 0 ? ' <span class="nav-badge">' . (int) $nb_notifs . '</span>' : '' ?></a>
      <?php if ($is_admin_header): ?>
        <a href="/Site_rencontre/RencontreIRL/app/admin/">Admin<?= $nb_reports > 0 ? ' <span class="nav-badge">' . (int) $nb_reports . '</span>' : '' ?></a>
      <?php endif; ?>
      <a href="/Site_rencontre/RencontreIRL/app/pages/parametres.php">Paramètres</a>
      <a href="/Site_rencontre/RencontreIRL/app/auth/deconnexion.php">Déconnexion</a>
    <?php else: ?>
      <a href="/Site_rencontre/RencontreIRL/app/auth/connexion.php">Connexion</a>
      <a href="/Site_rencontre/RencontreIRL/app/auth/inscription.php">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<main class="page-content">
