<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rencontre — Kindle Bloom</title>
  <link rel="stylesheet" href="/Site_rencontre/RencontreIRL/assets/css/style.css" />
</head>
<body>
<nav class="nav">
  <a href="/Site_rencontre/RencontreIRL/" class="nav-logo">Rencontre</a>
  <div class="nav-links">
    <?php if (isset($_SESSION['user_id'])): ?>
      <?php
      require_once $_SERVER['DOCUMENT_ROOT'] . '/Site_rencontre/RencontreIRL/config/db.php';

      $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
      $stmt_notif->execute([$_SESSION['user_id']]);
      $nb_notifs = $stmt_notif->fetchColumn();

      $stmt_msg = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
      $stmt_msg->execute([$_SESSION['user_id']]);
      $nb_msgs = $stmt_msg->fetchColumn();
      ?>
      <a href="/Site_rencontre/RencontreIRL/profil.php">Mon profil</a>
      <a href="/Site_rencontre/RencontreIRL/sorties.php">Sorties</a>
      <a href="/Site_rencontre/RencontreIRL/messages.php" style="position: relative;">
        Messages
      <?php if ($nb_msgs > 0): ?>
        <span style="
          position: absolute;
         top: -10px; right: -18px;
          background: #8b1a2a;
          color: white;
          border: 2px solid #fdf4f5;
          font-size: 10px;
          font-weight: 700;
          min-width: 18px;
          height: 18px;
          padding: 0 4px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          animation: pulse-badge 1.5s ease-in-out infinite;
        "><?= $nb_msgs ?></span>
      <?php endif; ?>
      </a>
      <a href="/Site_rencontre/RencontreIRL/notifications.php" style="position: relative;">
        Notifications
      <?php if ($nb_msgs > 0): ?>
        <span style="
          position: absolute;
         top: -10px; right: -18px;
          background: #8b1a2a;
          border: 2px solid #fdf4f5;
          color: white;
          font-size: 10px;
          font-weight: 700;
          min-width: 18px;
          height: 18px;
          padding: 0 4px;
          border-radius: 10px;
          display: flex;
          align-items: center;
          justify-content: center;
          animation: pulse-badge 1.5s ease-in-out infinite;
        "><?= $nb_msgs ?></span>
      <?php endif; ?>
      </a>
      <a href="/Site_rencontre/RencontreIRL/auth/deconnexion.php">Déconnexion</a>
    <?php else: ?>
      <a href="/Site_rencontre/RencontreIRL/auth/connexion.php">Connexion</a>
      <a href="/Site_rencontre/RencontreIRL/auth/inscription.php">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>