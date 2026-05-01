<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT b.blocked_id, b.created_at, u.prenom, u.ville, u.photo, u.account_status
    FROM user_blocks b
    JOIN users u ON u.id = b.blocked_id
    WHERE b.blocker_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$blocked_users = $stmt->fetchAll();
?>

<section class="section">
  <div class="section-header">
    <div>
      <a href="parametres.php" class="back-link">Retour aux parametres</a>
      <h1 class="section-title" style="margin-top: 0.75rem;">Profils bloques</h1>
    </div>
  </div>

  <?php if (empty($blocked_users)): ?>
    <div class="empty-state">
      <p>Tu n'as bloque aucun profil pour le moment.</p>
    </div>
  <?php else: ?>
    <div class="blocked-users-list">
      <?php foreach ($blocked_users as $blocked): ?>
        <article class="blocked-user-card">
          <div class="admin-user-main">
            <?php if (!empty($blocked['photo'])): ?>
              <img src="/Site_rencontre/RencontreIRL/public/uploads/<?= e($blocked['photo']) ?>" alt="Photo de profil" class="blocked-user-photo">
            <?php else: ?>
              <div class="conv-avatar"><?= e(strtoupper(substr($blocked['prenom'] ?: '?', 0, 1))) ?></div>
            <?php endif; ?>

            <div>
              <h2 class="admin-user-name"><?= e($blocked['prenom']) ?></h2>
              <p class="admin-report-meta">
                <?= e($blocked['ville'] ?: 'Ville non renseignee') ?> -
                bloque le <?= date('d/m/Y', strtotime($blocked['created_at'])) ?>
              </p>
              <p class="admin-report-meta">Statut : <?= e(account_status_label($blocked['account_status'] ?? 'active')) ?></p>
            </div>
          </div>

          <div class="admin-user-actions">
            <a href="profil-public.php?id=<?= (int) $blocked['blocked_id'] ?>" class="cta-btn-small">Voir le profil</a>
            <form method="POST" action="../actions/block-user.php" data-disable-on-submit="true">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="unblock">
              <input type="hidden" name="target_user_id" value="<?= (int) $blocked['blocked_id'] ?>">
              <input type="hidden" name="redirect" value="app/pages/blocked-users.php">
              <button type="submit" class="cta-btn-small">Debloquer</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
