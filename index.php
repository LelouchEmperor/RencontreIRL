<?php
require_once 'includes/header.php';
?>

<section class="hero-section">
  <div class="hero-content">
    <?php if (isset($_SESSION['user_id'])): ?>
      <h1 class="hero-title">Bonjour <?= htmlspecialchars($_SESSION['prenom']) ?> 👋</h1>
      <p class="hero-sub">Trouve quelqu'un pour faire ce que tu aimes — dans ta nouvelle ville.</p>
      <a href="sorties.php" class="cta-btn">Voir les sorties</a>
    <?php else: ?>
      <h1 class="hero-title">Trouve quelqu'un pour faire ce que tu aimes.</h1>
      <p class="hero-sub">Pas de swipe. Une vraie rencontre autour d'une activité commune.</p>
      <a href="auth/inscription.php" class="cta-btn">Commencer</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>