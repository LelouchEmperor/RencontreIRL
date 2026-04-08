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
      <a href="/Site_rencontre/RencontreIRL/profil.php">Mon profil</a>
      <a href="/Site_rencontre/RencontreIRL/sorties.php">Sorties</a>
      <a href="/Site_rencontre/RencontreIRL/messages.php">Messages</a>
      <a href="/Site_rencontre/RencontreIRL/auth/deconnexion.php">Déconnexion</a>
    <?php else: ?>
      <a href="/Site_rencontre/RencontreIRL/auth/connexion.php">Connexion</a>
      <a href="/Site_rencontre/RencontreIRL/auth/inscription.php">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>