<?php
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('app/auth/connexion.php'));
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.titre, s.date_sortie
    FROM sorties s
    LEFT JOIN participations p ON p.sortie_id = s.id
    WHERE (s.user_id = ? OR p.user_id = ?)
    AND s.date_sortie BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
    AND (s.status IS NULL OR s.status = '' OR s.status = 'open')
    LIMIT 10
");
$stmt->execute([$user_id, $user_id]);
$sorties_proches = $stmt->fetchAll();

foreach ($sorties_proches as $sortie_proche) {
    $lien = 'app/pages/sortie.php?id=' . (int) $sortie_proche['id'];
    $stmt = $pdo->prepare("
        SELECT id
        FROM notifications
        WHERE user_id = ?
        AND type = 'sortie_approche'
        AND lien = ?
        AND created_at >= (NOW() - INTERVAL 2 DAY)
        LIMIT 1
    ");
    $stmt->execute([$user_id, $lien]);

    if (!$stmt->fetch()) {
        $message = 'Ta sortie "' . texte_court($sortie_proche['titre'], 60) . '" approche.';
        $stmt_insert = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, lien)
            VALUES (?, 'sortie_approche', ?, ?)
        ");
        $stmt_insert->execute([$user_id, $message, $lien]);
    }
}

$stmt = $pdo->prepare("
    SELECT *
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$nb_non_lues = 0;
$types_notifications = [];

foreach ($notifications as $notif) {
    if ((int) $notif['lu'] === 0) {
        $nb_non_lues++;
    }

    $type = (string) ($notif['type'] ?? 'default');
    $types_notifications[$type] = ($types_notifications[$type] ?? 0) + 1;
}

$notification_labels = [
    'participation' => 'Participation',
    'participation_quittee' => 'Participation',
    'message' => 'Message',
    'sortie_approche' => 'Sortie proche',
    'sortie_modifiee' => 'Sortie modifiee',
    'sortie_annulee' => 'Sortie annulee',
    'report' => 'Moderation',
    'default' => 'Notification',
];

$stmt = $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ? AND lu = 0");
$stmt->execute([$user_id]);
?>

<section class="section">
  <div class="notifications-wrap">
    <div class="section-header">
      <div>
        <h1 class="section-title">Notifications</h1>
        <p class="sortie-meta"><?= count($notifications) ?> notification<?= count($notifications) > 1 ? 's' : '' ?> recente<?= count($notifications) > 1 ? 's' : '' ?></p>
      </div>
    </div>

    <div class="notifications-summary">
      <span><strong><?= (int) $nb_non_lues ?></strong> non lue<?= $nb_non_lues > 1 ? 's' : '' ?></span>
      <span><strong><?= (int) ($types_notifications['message'] ?? 0) ?></strong> message<?= ((int) ($types_notifications['message'] ?? 0)) > 1 ? 's' : '' ?></span>
      <span><strong><?= (int) (($types_notifications['participation'] ?? 0) + ($types_notifications['participation_quittee'] ?? 0)) ?></strong> participation<?= ((int) (($types_notifications['participation'] ?? 0) + ($types_notifications['participation_quittee'] ?? 0))) > 1 ? 's' : '' ?></span>
      <span><strong><?= (int) (($types_notifications['sortie_modifiee'] ?? 0) + ($types_notifications['sortie_annulee'] ?? 0) + ($types_notifications['sortie_approche'] ?? 0)) ?></strong> sortie<?= ((int) (($types_notifications['sortie_modifiee'] ?? 0) + ($types_notifications['sortie_annulee'] ?? 0) + ($types_notifications['sortie_approche'] ?? 0))) > 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="empty-state">
        <p>Aucune notification pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="notifications-list">
        <?php foreach ($notifications as $notif): ?>
          <?php
            $type = (string) ($notif['type'] ?? 'default');
            $label = $notification_labels[$type] ?? 'Notification';
          ?>
          <a href="<?= e(lien_interne($notif['lien'] ?? null)) ?>"
             class="notification-item notification-<?= e($notif['type'] ?? 'default') ?> <?= (int) $notif['lu'] === 0 ? 'is-unread' : '' ?>">
            <span class="notification-dot"></span>
            <div class="notification-content">
              <span class="notification-type"><?= e($label) ?></span>
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
