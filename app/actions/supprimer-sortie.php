<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/app/auth/connexion.php');
    exit;
}

$sortie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$sortie_id) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sorties WHERE id = ? AND user_id = ?");
$stmt->execute([$sortie_id, $_SESSION['user_id']]);
$sortie = $stmt->fetch();

if (!$sortie) {
    header('Location: /Site_rencontre/RencontreIRL/app/pages/sorties.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("DELETE FROM likes_sorties WHERE sortie_id = ?");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("DELETE FROM sortie_reviews WHERE sortie_id = ?");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("DELETE FROM participations WHERE sortie_id = ?");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("
            DELETE FROM reports
            WHERE target_type = 'message'
            AND target_id IN (SELECT id FROM messages WHERE sortie_id = ?)
        ");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("DELETE FROM messages WHERE sortie_id = ?");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("DELETE FROM reports WHERE target_type = 'sortie' AND target_id = ?");
        $stmt->execute([$sortie_id]);

        $stmt = $pdo->prepare("DELETE FROM sorties WHERE id = ? AND user_id = ?");
        $stmt->execute([$sortie_id, $_SESSION['user_id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    header('Location: /Site_rencontre/RencontreIRL/app/pages/profil.php');
    exit;
}
?>

<section class="auth-section">
  <div class="auth-card" style="max-width: 480px;">
    <h1 class="auth-title">Supprimer la sortie</h1>
    <p style="font-size: 14px; color: #7a5060; margin-bottom: 1.5rem; line-height: 1.7;">
      Tu es sur le point de supprimer <strong style="color: #1a0810;"><?= htmlspecialchars($sortie['titre']) ?></strong>. 
      Cette action est irréversible — les participants seront également retirés.
    </p>
    <form method="POST" data-disable-on-submit="true" action="" style="display: flex; gap: 1rem;">
      <?= csrf_field() ?>
      <a href="../pages/profil.php" class="cta-btn-outline" style="flex: 1; text-align: center;">Annuler</a>
      <button type="submit" style="flex: 1; padding: 13px; background: #8b1a2a; border: none; border-radius: 8px; color: #fdf4f5; font-size: 14px; cursor: pointer;">
        Supprimer
      </button>
    </form>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
