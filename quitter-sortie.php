<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ?");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();

if (!$sortie) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM participations WHERE sortie_id = ? AND user_id = ?");
$stmt->execute([$sortie_id, $user_id]);
$participation = $stmt->fetch();

if (!$participation) {
    header('Location: /Site_rencontre/RencontreIRL/sorties.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM participations WHERE sortie_id = ? AND user_id = ?");
        $stmt->execute([$sortie_id, $user_id]);

        $stmt = $pdo->prepare("UPDATE sorties SET places_restantes = places_restantes + 1 WHERE id = ?");
        $stmt->execute([$sortie_id]);

        $pdo->commit();
        header('Location: /Site_rencontre/RencontreIRL/profil.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $erreur = 'Une erreur est survenue. Réessaie.';
    }
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 480px;">
    <h1 class="auth-title">Quitter la sortie</h1>
    <p style="font-size: 14px; color: #7a5060; margin-bottom: 1.5rem; line-height: 1.7;">
      Tu es sur le point de quitter <strong style="color: #1a0810;"><?= htmlspecialchars($sortie['titre']) ?></strong>.
      Ta place sera remise à disposition.
    </p>

    <?php if (isset($erreur)): ?>
      <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="" style="display: flex; gap: 1rem;">
        <?= csrf_field() ?>
      <a href="profil.php" class="cta-btn-outline" style="flex: 1; text-align: center; padding: 13px;">Annuler</a>
      <button type="submit" style="flex: 1; padding: 13px; background: #8b1a2a; border: none; border-radius: 8px; color: #fdf4f5; font-size: 14px; cursor: pointer;">
        Quitter la sortie
      </button>
    </form>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>