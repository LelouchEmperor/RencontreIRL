<?php
require_once 'includes/header.php';
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Site_rencontre/RencontreIRL/auth/connexion.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?");
$stmt->execute([$user_id]);

$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
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
      <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($notifications as $notif): ?>
          <a href="<?= htmlspecialchars($notif['lien']) ?>"
             style="display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; background: <?= $notif['lu'] ? '#fff8f8' : '#fff0f2' ?>; border: 0.5px solid <?= $notif['lu'] ? '#e8c8cc' : '#d4909a' ?>; border-radius: 12px; text-decoration: none; transition: border-color 0.2s;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: <?= $notif['lu'] ? '#e8c8cc' : '#8b1a2a' ?>; flex-shrink: 0;"></div>
            <div style="flex: 1;">
              <div style="font-size: 14px; color: #1a0810;"><?= htmlspecialchars($notif['message']) ?></div>
              <div style="font-size: 11px; color: #a07080; margin-top: 0.25rem;"><?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>