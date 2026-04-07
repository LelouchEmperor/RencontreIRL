<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Rencontre — Kindle Bloom</title>
  <link rel="stylesheet" href="/rencontre_site_web/assets/css/style.css" />
</head>
<body>
<nav class="nav">
  <a href="/rencontre_site_web/" class="nav-logo">Rencontre</a>
  <div class="nav-links">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="/rencontre_site_web/profil.php">Mon profil</a>
      <a href="/rencontre_site_web/sorties.php">Sorties</a>
      <a href="/rencontre_site_web/messages.php">Messages</a>
      <a href="/rencontre_site_web/auth/deconnexion.php">Déconnexion</a>
    <?php else: ?>
      <a href="/rencontre_site_web/auth/connexion.php">Connexion</a>
      <a href="/rencontre_site_web/auth/inscription.php">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>