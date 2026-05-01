<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0");
$stmt->execute([$user_id]);
?>

<section class="section">
  <div style="max-width: 640px; margin: 0 auto;">
    <div class="section-header">
      <h1 class="section-title">Notifications</h1>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="empty-state">
        <p>Aucune notification pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="notifications-list">
        <?php foreach ($notifications as $notif): ?>
          <a href="<?= e(lien_interne($notif['lien'] ?? null)) ?>"
             class="notification-item <?= (int) $notif['lu'] === 0 ? 'is-unread' : '' ?>">
            <span class="notification-dot"></span>
            <div class="notification-content">
              <div class="notification-message"><?= e($notif['message']) ?></div>
              <div class="notification-date"><?= date('d/m/Y a H:i', strtotime($notif['created_at'])) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
