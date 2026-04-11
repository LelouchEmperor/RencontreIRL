<?php
require_once '../includes/header.php';
require_once '../config/db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (!$token) {
    header('Location: /Site_rencontre/RencontreIRL/');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE token_verification = ? AND email_verifie = 0");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    $stmt = $pdo->prepare("UPDATE users SET email_verifie = 1, token_verification = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    $succes = true;
} else {
    $succes = false;
}
?>

<section class="auth-section">
  <div class="auth-card" style="text-align: center;">
    <?php if ($succes): ?>
      <h1 class="auth-title">Email vérifié !</h1>
      <p style="font-size: 14px; color: #7a5060; margin-bottom: 1.5rem; line-height: 1.7;">
        Ton compte est maintenant actif. Tu peux te connecter.
      </p>
      <a href="connexion.php" class="cta-btn">Se connecter</a>
    <?php else: ?>
      <h1 class="auth-title">Lien invalide</h1>
      <p style="font-size: 14px; color: #7a5060; margin-bottom: 1.5rem;">
        Ce lien est invalide ou a déjà été utilisé.
      </p>
      <a href="../auth/inscription.php" class="cta-btn-outline">Créer un compte</a>
    <?php endif; ?>
  </div>
</section>

<?php require_once '../includes/footer.php'; ?>